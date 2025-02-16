<?php

namespace Rolukja\ViltCrudGenerator\Helper;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionMethod;

class RelationshipReader
{
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Gibt ein Array mit den Klassen zurück, die mit dem Model in Beziehung stehen, gruppiert nach Beziehungstyp.
     *
     * @return array
     */
    public function getRelatedClasses(): array
    {
        $relatedClasses = [
            'belongsTo' => [],
            'hasOne' => [],
            'hasMany' => [],
            'belongsToMany' => [],
            'morphTo' => [],
            'morphOne' => [],
            'morphMany' => [],
            'morphToMany' => [],
        ];

        $reflection = new ReflectionClass($this->model);

        // Durchlaufe die gesamte Klassenhierarchie
        while ($reflection) {
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Überprüfen, ob die Methode eine Eloquent-Beziehung zurückgibt
                $returnType = $method->getReturnType();
                if ($returnType && $this->isEloquentRelation($returnType->getName())) {
                    // Die Beziehung instanziieren und das zugehörige Model extrahieren
                    $relation = $method->invoke($this->model);
                    $relatedModel = $this->getRelatedModelFromRelation($relation);

                    if ($relatedModel) {
                        $relationType = $this->getRelationType($relation);
                        $relatedClasses[$relationType][$method->getName()] = $relatedModel;
                    }
                }
            }

            // Gehe zur Elternklasse
            $reflection = $reflection->getParentClass();
        }

        return $relatedClasses;
    }

    /**
     * Überprüft, ob der Rückgabetyp eine Eloquent-Beziehung ist.
     *
     * @param string $returnType
     * @return bool
     */
    protected function isEloquentRelation(string $returnType): bool
    {
        return is_a($returnType, 'Illuminate\Database\Eloquent\Relations\Relation', true);
    }

    /**
     * Extrahiert das zugehörige Model aus einer Eloquent-Beziehung.
     *
     * @param mixed $relation
     * @return string|null
     */
    protected function getRelatedModelFromRelation($relation): ?string
    {
        if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo ||
            $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne ||
            $relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany ||
            $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphOne ||
            $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphMany ||
            $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany ||
            $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphToMany) {
            return get_class($relation->getRelated());
        }

        if ($relation instanceof \Illuminate\Database\Eloquent\Relations\MorphTo) {
            return $relation->getMorphClass();
        }

        return null;
    }

    /**
     * Gibt den Typ der Eloquent-Beziehung zurück.
     *
     * @param mixed $relation
     * @return string
     */
    protected function getRelationType($relation): string
    {
        switch (true) {
            case $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo:
                return 'belongsTo';
            case $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne:
                return 'hasOne';
            case $relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany:
                return 'hasMany';
            case $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany:
                return 'belongsToMany';
            case $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphTo:
                return 'morphTo';
            case $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphOne:
                return 'morphOne';
            case $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphMany:
                return 'morphMany';
            case $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphToMany:
                return 'morphToMany';
            default:
                return 'unknown';
        }
    }
}
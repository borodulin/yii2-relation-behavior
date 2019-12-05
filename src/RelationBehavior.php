<?php

namespace yii\relations\Behaviors;

use yii\base\Behavior;
use yii\base\Component;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\base\UnknownPropertyException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 *
 * property yii\db\ActiveRecord $owner
 * property yii\db\ActiveQuery $relations
 *
 * @property array $relations
 */
class RelationBehavior extends Behavior
{
    private $_values = [];

    public $linkDelete = true;

    /**
     * ['ids_property' => 'relation', 'relation']
     * @var array
     */
    public $relations;

    /**
     * @param Component|ActiveRecord $owner
     * @throws InvalidConfigException
     */
    public function attach($owner)
    {
        if (!$owner instanceof ActiveRecord) {
            throw new InvalidConfigException('RelationBehavior should be attached to ActiveRecord instance');
        }

        if (!is_array($this->relations)) {
            throw new InvalidConfigException();
        }
        foreach ($this->relations as $key => $relationName) {
            if (is_int($key)) {
                $this->relations[$relationName] = $relationName;
                unset($this->relations[$key]);
            }
        }
        parent::attach($owner);
    }

    /**
     * {@inheritDoc}
     * @see \yii\base\Behavior::events()
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'changeRelations',
            ActiveRecord::EVENT_AFTER_UPDATE => 'changeRelations',
        ];
    }

    /**
     *
     */
    public function changeRelations()
    {
        /* @var $owner ActiveRecord */
        $owner = $this->owner;
        foreach ($this->_values as $name => $values) {
            $relationName = $this->relations[$name];
            /* @var $relation ActiveQuery */
            $relation = $owner->getRelation($relationName);
            if ($relation->multiple) {
                if (empty($values)) {
                    $owner->unlinkAll($relationName, $this->linkDelete);
                } else {
                    /** @var ActiveRecord $modelClass */
                    $modelClass = $relation->modelClass;
                    $primaryKey = $modelClass::primaryKey();
                    $currentKeys = ArrayHelper::getColumn($owner->$relationName, $primaryKey);
                    $linkedModels = $modelClass::findAll($values);
                    $linkedKeys = ArrayHelper::getColumn($linkedModels, $primaryKey);
                    /** @var ActiveRecord $rel */
                    foreach ($owner->$relationName as $rel) {
                        $pk = ArrayHelper::getValue($rel, $primaryKey);
                        if (!in_array($pk, $linkedKeys)) {
                            $owner->unlink($relationName, $rel, $this->linkDelete);
                        }
                    }
                    foreach ($linkedModels as $rel) {
                        $pk = ArrayHelper::getValue($rel, $primaryKey);
                        if (!in_array($pk, $currentKeys)) {
                            $owner->link($relationName, $rel);
                        }
                    }
                }
            } else {
                throw new InvalidCallException('Relation type is not supported.');
            }
        }
    }

    /**
     * @param string $name
     * @param bool $checkVars
     * @return bool
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return isset($this->relations[$name]) || parent::canGetProperty($name, $checkVars);
    }

    /**
     * @param string $name
     * @return mixed|null
     * @throws UnknownPropertyException
     */
    public function __get($name)
    {
        if (isset($this->relations[$name])) {
            if (isset($this->_values[$name])) {
                return $this->_values[$name];
            }
            /** @var ActiveRecord $owner */
            $owner = $this->owner;
            $relationName = $this->relations[$name];
            $relation = $owner->getRelation($relationName);
            /** @var ActiveRecord $modelClass */
            $modelClass = $relation->modelClass;
            return ArrayHelper::getColumn($owner->$relationName, $modelClass::primaryKey());
        }
        return parent::__get($name);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \yii\base\Object::canSetProperty()
     */
    public function canSetProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        return isset($this->relations[$name]) || parent::canSetProperty($name, $checkVars);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \yii\base\Object::__set()
     */
    public function __set($name, $value)
    {
        if (isset($this->relations[$name])) {
            $this->_values[$name] = $value;
        } else {
            parent::__set($name, $value);
        }
    }
}

<?php

namespace tinkers;

use Yii;
use mohorev\file\UploadBehavior as MohorevUploadBehavior;
use yii\db\BaseActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class UploadBehavior extends MohorevUploadBehavior
{

    /**
     * @var string path attribute to hold path of attachment
     */
    public $pathAttribute;
    /**
     * @var bool if field is multilingual or not
     */
    public $isMultilingual = false;
    /**
     * @var string if field is multilingual then provide language code
     */
    public $language = '';

    protected $multiLingualAttribute = null;

    private $originalAttribute = null;

    /**
     * @var UploadedFile the uploaded file instance.
     */
    private $_file;

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if ($this->isMultilingual) {
            $this->originalAttribute = $this->attribute;
            $this->attribute = \yeesoft\multilingual\helpers\MultilingualHelper::getAttributeName($this->attribute, $this->language);
        }
    }

    /**
     * This method is invoked before validation starts.
     */
    public function beforeValidate()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        if (in_array($model->scenario, $this->scenarios)) {
            if (($file = $model->getAttribute($this->attribute)) instanceof UploadedFile) {
                $this->_file = $file;
            } else {
                if ($this->instanceByName === true) {
                    $this->_file = UploadedFile::getInstanceByName($this->attribute);
                } else {
                    $this->_file = UploadedFile::getInstance($model, $this->attribute);
                }
            }

            if ($this->_file instanceof UploadedFile && $model->hasAttribute($this->attribute)) {
                $this->_file->name = $this->getFileName($this->_file);
                $model->setAttribute($this->attribute, $this->_file);
            } elseif ($this->_file instanceof UploadedFile && !$model->hasAttribute($this->attribute) && isset($model->{$this->attribute})) {
                $this->_file->name = $this->getFileName($this->_file);
                $model->{$this->attribute} = $this->_file;
            }
        }
    }

    /**
     * This method is called at the beginning of inserting or updating a record.
     */
    public function beforeSave()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        if (in_array($model->scenario, $this->scenarios)) {
            if ($this->_file instanceof UploadedFile) {

                if (!$model->getIsNewRecord() && $model->isAttributeChanged($this->attribute)) {
                    if ($this->unlinkOnSave === true) {
                        $this->delete($this->attribute, true);
                    }
                }

                // if multilingual
                if (!$model->getIsNewRecord() && ($this->isMultilingual && $model->translation->{$this->originalAttribute} !== $model->{$this->attribute})) {
                    if ($this->unlinkOnSave === true) {

                        $this->delete($this->originalAttribute, true);
                    }
                }

                if ($model->hasAttribute($this->attribute)) {
                    $model->setAttribute($this->attribute, $this->_file->name);
                } else {
                    $model->{$this->attribute} = $this->_file->name;
                }

            } else {
                // Protect attribute
                unset($model->{$this->attribute});
            }
        } else {
            if (!$model->getIsNewRecord() && $model->isAttributeChanged($this->attribute)) {
                if ($this->unlinkOnSave === true) {
                    $this->delete($this->attribute, true);
                }
            }
        }
    }

    /**
     * This method is called at the end of inserting or updating a record.
     * @throws \yii\base\InvalidArgumentException
     */
    public function afterSave()
    {
        $model = $this->owner;
        if ($this->_file instanceof UploadedFile) {

            if (!$this->isMultilingual)
                $path = $this->getUploadPath($this->attribute);
            else
                $path = $this->getUploadPath($this->originalAttribute);
            $pathUrl = $this->getSavableUrl();
            if (is_string($path) && FileHelper::createDirectory(dirname($path))) {
                $this->save($this->_file, $path);


                if (isset($this->pathAttribute) && !empty($this->pathAttribute) && $model->hasAttribute($this->pathAttribute)) {
                    $model->updateAttributes([$this->pathAttribute => $pathUrl]);
                }

                $this->afterUpload();
            } else {
                throw new InvalidArgumentException(
                    "Directory specified in 'path' attribute doesn't exist or cannot be created."
                );
            }
        }
    }

    /**
     * Deletes old file.
     * @param string $attribute
     * @param boolean $old
     */
    protected function delete($attribute, $old = false)
    {
        $path = $this->getUploadPath($attribute, $old, true);
        if (is_file($path)) {
            unlink($path);
        }
    }

    protected function getSavableUrl()
    {
        $url = $this->resolvePath($this->url);
        return Yii::getAlias($url);
    }

    /**
     * Returns file path for the attribute.
     * @param string $attribute
     * @param boolean $old
     * @return string|null the file path.
     */
    public function getUploadPath($attribute, $old = false, $isDeletion = false)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        $path = $this->resolvePath($this->path);

        if (!$isDeletion) {
            $fileName = ($old === true) ? $model->getOldAttribute($attribute) : $model->$attribute;
        } else {
            if (!$this->isMultilingual) {
                $fileName = ($old === true) ? $model->getOldAttribute($attribute) : $model->$attribute;
            } else {
                $fileName = ($old === true) ? $model->translation->getOldAttribute($attribute) : $model->translation->$attribute;
            }
        }

        return $fileName ? Yii::getAlias($path . '/' . $fileName) : null;
    }

    public function afterDelete()
    {
        if (!$this->isMultilingual) {
            $attribute = $this->attribute;
        } else {
            $attribute = $this->originalAttribute;
        }

        if ($this->unlinkOnDelete && $attribute) {
            if (!$this->isMultilingual)
                $this->delete($attribute);
            else
                $this->delete($attribute, true);
        }
    }

    /**
     * Returns the UploadedFile instance.
     * @return UploadedFile
     */
    protected function getUploadedFile()
    {
        return $this->_file;
    }

}

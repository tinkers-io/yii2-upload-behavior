<?php

namespace tinkers;

use mohorev\file\UploadBehavior as MohorevUploadBehavior;
use yii\db\BaseActiveRecord;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class UploadBehavior extends MohorevUploadBehavior
{

    /**
     * @var string path attribute to hold path of attachment
     */
    public $pathAttribute;

    /**
     * @var UploadedFile the uploaded file instance.
     */
    private $_file;

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

                if($model->hasAttribute($this->attribute)) {
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
            $path = $this->getUploadPath($this->attribute);
            $pathUrl = $this->getSavableUrl();
            if (is_string($path) && FileHelper::createDirectory(dirname($path))) {
                $this->save($this->_file, $path);


                if (isset($this->pathAttribute) && !empty($this->pathAttribute) && $model->hasAttribute($this->pathAttribute)) {
                    $model->updateAttributes([$this->pathAttribute => str_ireplace(basename($pathUrl), '', $pathUrl)]);
                }

                $this->afterUpload();
            } else {
                throw new InvalidArgumentException(
                    "Directory specified in 'path' attribute doesn't exist or cannot be created."
                );
            }
        }
    }

    protected function getSavableUrl()
    {
        $path = $this->resolvePath($this->url);
        $pathUrl = \Yii::getAlias($path);

        return $pathUrl;
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

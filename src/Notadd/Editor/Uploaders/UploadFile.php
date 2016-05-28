<?php
/**
 * This file is part of Notadd.
 * @author TwilRoad <269044570@qq.com>
 * @copyright (c) 2015, iBenchu.org
 * @datetime 2016-05-12 11:05
 */
namespace Notadd\Editor\Uploaders;
use Illuminate\Container\Container;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
/**
 * Class UploadFile
 * @package Notadd\Editor\Uploaders
 */
class UploadFile extends AbstractUpload {
    /**
     * @return bool
     */
    public function doUpload() {
        $file = $this->request->file($this->fileField);
        if(empty($file)) {
            $this->stateInfo = $this->getStateInfo("ERROR_FILE_NOT_FOUND");
            return false;
        }
        if(!$file->isValid()) {
            $this->stateInfo = $this->getStateInfo($file->getError());
            return false;
        }
        $this->file = $file;
        $this->oriName = $this->file->getClientOriginalName();
        $this->fileSize = $this->file->getSize();
        $this->fileType = $this->getFileExt();
        $this->fullName = $this->getFullName();
        $this->filePath = $this->getFilePath();
        $this->fileName = basename($this->filePath);
        if(!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return false;
        }
        if(!$this->checkType()) {
            $this->stateInfo = $this->getStateInfo("ERROR_TYPE_NOT_ALLOWED");
            return false;
        }
        try {
            $this->file->move(dirname($this->filePath), $this->fileName);
            if($this->fileType != '.webp') {
                $this->filePath = str_replace($this->fileType, '.webp', $this->getFilePath());
                if(Container::getInstance()->make('setting')->get('attachment.watermark', false) && Container::getInstance()->make('files')->exists($this->config['watermark'])) {
                    $this->image->make($this->getFilePath())->insert($this->config['watermark'], 'center')->save($this->filePath);
                } else {
                    $this->image->make($this->getFilePath())->save($this->filePath);
                }
                $this->oriName = str_replace($this->fileType, '.webp', $this->oriName);
                $this->fileName = str_replace($this->fileType, '.webp', $this->fileName);
                $this->fullName = str_replace($this->fileType, '.webp', $this->fullName);
                $this->fileType = '.webp';
            }
            $this->stateInfo = $this->stateMap[0];
        } catch(FileException $exception) {
            $this->stateInfo = $this->getStateInfo("ERROR_WRITE_CONTENT");
            return false;
        }
        return true;
    }
}
<?php


class S3AssetManager extends CAssetManager
{

    public $bucket;
    public $path;
    public $host;
    public $s3Component = 's3';
    public $cacheComponent = 'cache';
    private $_baseUrl;
    private $_basePath;
    private $_published;

    public function getBasePath()
    {
        if ($this->_basePath === null)
        {
            $this->_basePath = $this->path;
        }
        return $this->_basePath;
    }

    public function getBaseUrl()
    {
        if ($this->_baseUrl === null)
        {
            $this->_baseUrl = 'http://'.$this->host.'/'.$this->path;
        }
        return $this->_baseUrl;
    }

    private function getCache()
    {
        if (!Yii::app()->{$this->cacheComponent})
            throw new CException('You need to configure a cache storage or set the variable cacheComponent');

        return Yii::app()->{$this->cacheComponent};
    }

    private function getS3()
    {
        if (!Yii::app()->{$this->s3Component})
        	throw new CException('You need to configure the S3 component or set the variable s3Component properly');
        return Yii::app()->{$this->s3Component};
    }

    private function getCacheKey($path)
    {
        return $this->hash(Yii::app()->request->serverName).'.'.$path;
    }

    public function publish($path, $hashByName=false, $level=-1, $forceCopy=false)
    {
        if (isset($this->_published[$path]))
            return $this->_published[$path];
        else if (($src = realpath($path)) !== false)
        {
            if (is_file($src))
            {
                $dir = $this->hash($hashByName ? basename($src) : dirname($src).filemtime($src));
                $fileName = basename($src);
                $dstDir = $this->getBasePath().'/'.$dir;
                $dstFile = $dstDir.'/'.$fileName;
                if ($this->getCache()->get($this->getCacheKey($path)) === false)
                {
                    if ($this->getS3()->putObjectFile($src, $this->bucket, $dstFile, $acl = S3::ACL_PUBLIC_READ))
                    {
                        $this->getCache()->set($this->getCacheKey($path), true, 0, new CFileCacheDependency($src));
                    }
                    else
                    {
                        throw new CException('Could not send asset do S3');
                    }
                }

                return $this->_published[$path] = $this->getBaseUrl()."/$dir/$fileName";
            }
            else if (is_dir($src))
            {
                $dir = $this->hash($hashByName ? basename($src) : $src.filemtime($src));
                $dstDir = $this->getBasePath().DIRECTORY_SEPARATOR.$dir;

                if ($this->getCache()->get($this->getCacheKey($path)) === false)
                {
                    $files = CFileHelper::findFiles($src, array(
                            'exclude' => $this->excludeFiles,
                            'level' => $level,
                            )
                    );

                    foreach ($files as $f)
                    {
                        $dstFile = $this->getBasePath().'/'.$dir.'/'.str_replace($src.DIRECTORY_SEPARATOR, "", $f);

                        if (!$this->getS3()->putObjectFile($f, $this->bucket, $dstFile, $acl = S3::ACL_PUBLIC_READ))
                        {
                            throw new CException('Could not send assets do S3');
                        }
                    }

                    $this->getCache()->set($this->getCacheKey($path), true, 0, new CDirectoryCacheDependency($src));
                }


                return $this->_published[$path] = $this->getBaseUrl().'/'.$dir;
            }
        }
        throw new CException(Yii::t('yii', 'The asset "{asset}" to be published does not exist.', array('{asset}' => $path)));
    }

}

?>

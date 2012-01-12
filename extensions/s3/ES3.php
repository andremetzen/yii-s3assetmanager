<?php

 class ES3 extends CApplicationComponent
{

	private $_s3;
	public $aKey;
	public $sKey;
	public $bucket;
	public $lastError="";

	private function getInstance(){
		if ($this->_s3 === NULL)
			$this->connect();
		return $this->_s3;
	}
    
	public function connect()
	{
		if ( $this->aKey === NULL || $this->sKey === NULL )
			throw new CException('S3 Keys are not set.');
			
		$this->_s3 = new S3($this->aKey,$this->sKey);
	}

    public function __call($name, $arguments)
    {
        if(method_exists($this->getInstance(), $name))
        {
            return call_user_func_array(array($this->getInstance(), $name), $arguments);
        }
        else
        {
            throw new CException('Method "'.$name.'" does not exists');
        }
    }


}
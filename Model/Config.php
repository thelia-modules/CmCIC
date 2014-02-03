<?php

namespace CmCIC\Model;

use CmCIC\CmCIC;
use Thelia\Core\Translation\Translator;

class Config implements ConfigInterface {
    protected $CMCIC_TPE=null;
    protected $CMCIC_KEY=null;
    protected $CMCIC_CODESOCIETE=null;
    protected $CMCIC_VERSION=null;
    protected $CMCIC_SERVER=null;
    protected $CMCIC_URLOK=null;
    protected $CMCIC_URLKO=null;
    protected $CMCIC_PAGE=null;

    public function __construct()
    {
        $config=null;
        try {
            $config=$this->read();
        } catch(\Exception $e) {}
        if($config !== null) {
            foreach($config as $key=>$val) {
                try {
                    $this->__set($key,$val);
                } catch(\Exception $e) {}
            }
        }
    }

    public function write($file=null) {
        $path = __DIR__."/../".$file;
        if((file_exists($path) ? is_writable($path):is_writable(__DIR__."/../Config/"))) {
            $vars= get_object_vars($this);
            $cond = true;
            foreach($vars as $key=>$var)
                $cond &= !empty($var);
            if($cond) {
                $file = fopen($path, 'w');
                fwrite($file, json_encode($vars));
                fclose($file);
            }
        } else {
            throw new \Exception(Translator::getInstance()->trans("Can't write file ").$file.". ".
                Translator::getInstance()->trans("Please change the rights on the file and/or directory."));

        }
    }
    /**
     * @return array
     */
    public static function read($file=null) {
        $path = __DIR__."/../".$file;
        $ret = null;
        if(is_readable($path)) {
            $json = json_decode(file_get_contents($path), true);
            if($json !== null) {
                $ret = $json;
            } else {
                throw new \Exception(Translator::getInstance()->trans("Can't read file ").$file.". ".
                    Translator::getInstance()->trans("The file is corrupted."));
            }
        } elseif(!file_exists($path)) {
            throw new \Exception(Translator::getInstance()->trans("The file ").$file.
                                Translator::getInstance()->trans(" doesn't exist. You have to create it in order to use this module. PLease see module's configuration page."));
        } else {
            throw new \Exception(Translator::getInstance()->trans("Can't read file ").$file.". ".
                                Translator::getInstance()->trans("Please change the rights on the file."));

        }
        return $ret;
    }


    /**
     * @param string $CMCIC_PAGE
     */
    public function setCMCICPAGE($CMCIC_PAGE)
    {
        $this->CMCIC_PAGE = $CMCIC_PAGE;
        return $this;
    }

    /**
     * @param string $CMCIC_KEY
     */
    public function setCMCICKEY($CMCIC_KEY)
    {
        $this->CMCIC_KEY = $CMCIC_KEY;
        return $this;
    }

    /**
     * @param string $CMCIC_CODESOCIETE
     */
    public function setCMCICCODESOCIETE($CMCIC_CODESOCIETE)
    {
        $this->CMCIC_CODESOCIETE = $CMCIC_CODESOCIETE;
        return $this;
    }
    /**
     * @param string $CMCIC_SERVEUR
     */
    public function setCMCICSERVER($CMCIC_SERVER)
    {
        $this->CMCIC_SERVER = $CMCIC_SERVER;
        return $this;
    }

    /**
     * @param string $CMCIC_TPE
     */
    public function setCMCICTPE($CMCIC_TPE)
    {
        $this->CMCIC_TPE = $CMCIC_TPE;
        return $this;
    }

    /**
     * @param string $CMCIC_URLKO
     */
    public function setCMCICURLKO($CMCIC_URLKO)
    {
        $this->CMCIC_URLKO = $CMCIC_URLKO;
        return $this;
    }

    /**
     * @param string $CMCIC_URLOK
     */
    public function setCMCICURLOK($CMCIC_URLOK)
    {
        $this->CMCIC_URLOK = $CMCIC_URLOK;
        return $this;
    }

    /**
     * @param string $CMCIC_VERSION
     */
    public function setCMCICVERSION($CMCIC_VERSION)
    {
        $this->CMCIC_VERSION = $CMCIC_VERSION;
        return $this;
    }
}


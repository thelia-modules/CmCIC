<?php
namespace CmCIC\Model;

interface ConfigInterface {
    // Data access
    public function write($file=null);
    public static function read($file=null);

    // variables setters
    /*
     * @return CmCIC\Model\ConfigInterface
     */
    public function setCMCICPAGE($CMCIC_PAGE);
    
    /*
     * @return CmCIC\Model\ConfigInterface
     */
    public function setCMCICKEY($CMCIC_KEY);
    
    /*
     * @return CmCIC\Model\ConfigInterface
     */
    public function setCMCICCODESOCIETE($CMCIC_CODESOCIETE);
    
    /*
     * @return CmCIC\Model\ConfigInterface
     */
    public function setCMCICSERVER($CMCIC_SERVER);
    
    /*
     * @return CmCIC\Model\ConfigInterface
     */
    public function setCMCICTPE($CMCIC_TPE);
    
    /*
     * @return CmCIC\Model\ConfigInterface
     */
    public function setCMCICURLKO($CMCIC_URLKO);
    
    /*
     * @return CmCIC\Model\ConfigInterface
     */
    public function setCMCICURLOK($CMCIC_URLOK);
    
    /*
     * @return CmCIC\Model\ConfigInterface
     */
    public function setCMCICVERSION($CMCIC_VERSION);

    /*
     * @return CmCIC\Model\ConfigInterface
     */
    public function setCMCICURLRECEIVE($CMCIC_URLRECEIVE);
}

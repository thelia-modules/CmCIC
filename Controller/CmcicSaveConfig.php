<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace CmCIC\Controller;

use CmCIC\CmCIC;
use CmCIC\Form\ConfigureCmCIC;
use CmCIC\Model\Config;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

#[Route('/admin/module/cmcic', name: 'cmcic_save_config_')]
class CmcicSaveConfig extends BaseAdminController
{
    public const CIC_SERVER = "https://ssl.paiement.cic-banques.fr/";
    public const CM_SERVER = "https://paiement.creditmutuel.fr/";
    public const OBC_SERVER = "https://ssl.paiement.banque-obc.fr/";
    public const MONETICO_SERVER = "https://p.monetico-services.com/";

    public const CMCIC_VERSION = "3.0";

    #[Route('/log', name: 'download_log')]
    public function downloadLog()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CmCIC', AccessManager::UPDATE)) {
            return $response;
        }

        $data = @file_get_contents(THELIA_LOG_DIR . "log-cmcic.txt");

        if (empty($data)) {
            $data = Translator::getInstance()->trans("The CmCIC server log is currently empty.", [], CmCIC::DOMAIN_NAME);
        }
        return new Response(
            $data,
            200,
            array(
                'Content-type' => "text/plain",
                'Content-Disposition' => 'Attachment;filename=log-cmcic.txt'
            )
        );
    }

    #[Route('/saveconfig', name: 'save_config', methods: ['POST'])]
    public function save()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CmCIC', AccessManager::UPDATE)) {
            return $response;
        }

        $conf = new Config();
        $form = $this->createForm(ConfigureCmCIC::getName());

        try {
            $vform = $this->validateForm($form);

            CmCIC::setConfigValue('debug', $vform->get('debug')->getData());
            CmCIC::setConfigValue('allowed_ips', $vform->get('allowed_ips')->getData());
            CmCIC::setConfigValue('send_confirmation_message_only_if_paid', $vform->get('send_confirmation_message_only_if_paid')->getData());

            // After post-checks (PREG_MATCH) & create json file
            if (preg_match("#^\d{7}$#", $vform->get('TPE')->getData()) &&
                preg_match("#^[a-z\d]{40}$#i", $vform->get('com_key')->getData()) &&
                preg_match("#^[a-z\-\d]+$#i", $vform->get('com_soc')->getData()) &&
                preg_match("#^cic|cm|obc|mon$#", $vform->get('server')->getData())
            ) {
                $serv = $vform->get('server')->getData();

                $serv = match ($serv) {
                    'mon' => self::MONETICO_SERVER,
                    'cic' => self::CIC_SERVER,
                    'cm' => self::CM_SERVER,
                    'obc' => self::OBC_SERVER,
                    default => throw new InvalidArgumentException("Unknown server type '$serv'"),
                };

                if ($vform->get('debug')->getData() === true) {
                    $serv .= 'test/';
                }

                $conf
                    ->setCMCICKEY($vform->get('com_key')->getData())
                    ->setCMCICVERSION(self::CMCIC_VERSION)
                    ->setCMCICCODESOCIETE($vform->get('com_soc')->getData())
                    ->setCMCICPAGE($vform->get('page')->getData())
                    ->setCMCICTPE($vform->get('TPE')->getData())
                    ->setCMCICSERVER($serv)
                    ->write(CmCIC::JSON_CONFIG_PATH)
                ;
            } else {
                throw new RuntimeException(Translator::getInstance()->trans("Error in form syntax, please check that your values are correct."));
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();

            $this->setupFormErrorContext(
                'erreur sauvegarde configuration',
                $error_message,
                $form
            );
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl("/admin/module/CmCIC"));
    }
}

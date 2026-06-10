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

/**
 * Created by Franck Allimant, CQFDev <franck@cqfdev.fr>
 * Date: 13/09/2019 09:41
 */
namespace CmCIC\Hook;

use CmCIC\Form\ConfigureCmCIC;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Translation\Translator;

class HookManager extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public function onModuleConfigure(HookRenderEvent $event): void
    {
        $form = $this->formFactory->createForm(ConfigureCmCIC::getName());

        $event->add(
            $this->render('CmCIC/module-configuration.html.twig', [
                'form' => $form->createView()->getView(),
                'rights_errors' => $this->checkConfigRights(),
            ])
        );
    }

    /**
     * @return array<int, array{message: string, file: string}>
     */
    private function checkConfigRights(): array
    {
        $translator = Translator::getInstance();
        $errors = [];
        $dir = __DIR__ . '/../Config/';

        if (!is_readable($dir)) {
            $errors[] = ['message' => $translator->trans("Can't read Config directory"), 'file' => ''];
        }
        if (!is_writable($dir)) {
            $errors[] = ['message' => $translator->trans("Can't write Config directory"), 'file' => ''];
        }

        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if (strlen($file) > 5 && str_ends_with($file, '.json')) {
                    if (!is_readable($dir . $file)) {
                        $errors[] = ['message' => $translator->trans("Can't read file"), 'file' => 'CmCIC/Config/' . $file];
                    }
                    if (!is_writable($dir . $file)) {
                        $errors[] = ['message' => $translator->trans("Can't write file"), 'file' => 'CmCIC/Config/' . $file];
                    }
                }
            }
            closedir($handle);
        }

        return $errors;
    }

    public static function getSubscribedHooks(): array
    {
        return [
            "module.configuration" => [
                [
                    "type" => "back",
                    "method" => "onModuleConfigure"
                ]
            ],
        ];
    }
}

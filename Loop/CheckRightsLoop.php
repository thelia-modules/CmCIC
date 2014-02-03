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

namespace CmCIC\Loop;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;

/**
 * Class CheckRightsLoop
 * @package CmCIC\Looop
 * @author Thelia <info@thelia.net>
 */

class CheckRightsLoop extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions()
    {
        return new ArgumentCollection();
    }

    public function buildArray()
    {
        $ret = array();
        $dir = __DIR__."/../Config/";
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if (strlen($file) > 5 && substr($file, -5) === ".json") {
                    if (!is_readable($dir.$file)) {
                        $ret[] = array("ERRMES"=>"Can't read file", "ERRFILE"=>"CmCIC/Config/".$file);
                    }
                    if (!is_writable($dir.$file)) {
                        $ret[] = array("ERRMES"=>"Can't write file", "ERRFILE"=>"CmCIC/Config/".$file);
                    }
                }
            }
        } else {
            $ret[] = array("ERRMES"=>"Can't read Config directory", "ERRFILE"=>"");
        }

        return $ret;
    }
    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $arr) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("ERRMES", $arr["ERRMES"])
                ->set("ERRFILE", $arr["ERRFILE"]);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}

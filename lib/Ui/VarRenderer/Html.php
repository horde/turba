<?php

/**
 * This file contains all Horde_Core_Ui_VarRenderer extensions required for
 * editing contacts.
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @package Turba
 */

/**
 * The Turba_Ui_VarRenderer_Html class provides additional methods for
 * rendering Turba specific fields.
 *
 * @todo    Clean this hack up with Horde_Form/H4
 * @author  Jan Schneider <jan@horde.org>
 * @package Turba
 */
class Turba_Ui_VarRenderer_Html extends Horde_Core_Ui_VarRenderer_Html
{
    /**
     * Render tag field.
     */
    protected function _renderVarInput_TurbaTags($form, $var, $vars)
    {
        $varname = htmlspecialchars((string) $var->getVarName());
        $value = htmlspecialchars((string) $var->getValue($vars));

        $html = sprintf('<input id="%s" type="text" name="%s" value="%s" />', $varname, $varname, $value);
        $html .= sprintf(
            '<span id="%s_loading_img" style="display:none;">%s</span>',
            $varname,
            Horde_Themes_Image::tag('loading.gif', ['alt' => _("Loading...")])
        );

        $GLOBALS['injector']->getInstance('Horde_Core_Factory_Imple')->create('Turba_Ajax_Imple_TagAutoCompleter', ['id' => $varname]);
        return $html;
    }
}

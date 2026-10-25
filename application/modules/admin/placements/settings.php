<?php
/**
 * Content Placements — global settings.
 * SPDX-License-Identifier: MIT
 */

namespace IPS\rqblockplacements\modules\admin\placements;

use IPS\Dispatcher;
use IPS\Dispatcher\Controller;
use IPS\Helpers\Form;
use IPS\Helpers\Form\YesNo;
use IPS\Http\Url;
use IPS\Member;
use IPS\Output;
use IPS\rqblockplacements\Rule;
use IPS\rqblockplacements\RuleAccess;
use IPS\Session;
use IPS\Settings as IPSSettings;

if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

class settings extends Controller
{
    public static bool $csrfProtected = true;

    public function execute(): void
    {
        Dispatcher::i()->checkAcpPermission('rqbp_manage');
        parent::execute();
    }

    protected function manage(): void
    {
        $form = new Form('rqbp_settings_form');
        $form->addMessage('rqbp_settings_intro');
        $form->add(new YesNo('rqbp_enabled', (bool) IPSSettings::i()->rqbp_enabled));
        if ($values = $form->values()) {
            RuleAccess::assertMasterEnable((bool) IPSSettings::i()->rqbp_enabled, (bool) $values['rqbp_enabled']);
            IPSSettings::i()->changeValues(['rqbp_enabled' => (int) $values['rqbp_enabled']]);
            Rule::changed();
            Session::i()->log('rqbp_log_settings');
            Output::i()->redirect(Url::internal('app=rqblockplacements&module=placements&controller=settings'), 'saved');
        }
        Output::i()->title = Member::loggedIn()->language()->addToStack('rqbp_settings');
        Output::i()->sidebar['actions']['rules'] = [
            'title' => 'rqbp_rules', 'icon' => 'list',
            'link' => Url::internal('app=rqblockplacements&module=placements&controller=rules'),
        ];
        Output::i()->output = (string) $form;
    }
}

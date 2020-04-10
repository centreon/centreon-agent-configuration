<?php

/*
 * Copyright 2016-2020 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

if (!isset($centreon)) {
    exit();
}

require_once __DIR__ . "/DB-Func.php";

if (($o == "c") && $agentId) {
    /*
    * Set base value
    */
    $dbResult = $pearDB->query(
        "SELECT options.value FROM options WHERE options.key = 'config_agent_" . (int)$agentId . "' LIMIT 1"
    );
    $agent = json_decode($dbResult->fetch()['value'], true);

    $agent['poller_id'] = $agent['id'];
    $agent['use_proxy']['use_proxy'] = $agent['use_proxy'];
    $agent['insecure_ssl']['insecure_ssl'] = $agent['no_ssl'];
    $agent['is_gateway']['is_gateway'] = $agent['is_gateway'];
    $agent['use_gateway']['use_gateway'] = $agent['use_gateway'];
    $agent['activate']['activate'] = $agent['activate'];
    $agent['listening_port'] = $agent['listen'];
} else {
    $agent = [
        'listening_port' => '31281',
    ];
}

// Var information to format the element
$attrsText = array("size" => "30");
$attrsText2 = array("size" => "6");
$attrsTextURL = array("size" => "50");
$attrsAdvSelect_small = array("style" => "width: 270px; height: 70px;");
$attrsAdvSelect = array("style" => "width: 270px; height: 100px;");
$attrsAdvSelect_big = array("style" => "width: 270px; height: 200px;");
$attrsTextarea = array("rows" => "5", "cols" => "40");

$url = "?p=" . $p;
if (isset($o)) {
    $url  .= "&o=" . $o;
}
if (isset($agentId)) {
    $url .= "&agent_id=" . $agentId;
}

//Form begin
$form = new HTML_QuickFormCustom('Form', 'post', $url );
if ($o == "a") {
    $form->addElement('header', 'title', _("Add an agent configuration"));
} elseif ($o == "c") {
    $form->addElement('header', 'title', _("Modify an agent configuration"));
}

/*
 * Basic information
 */
$attrPollers = array(
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => './api/internal.php?object=centreon_configuration_poller&action=list',
    'multiple' => false,
    'linkedObject' => 'centreonInstance'
);
$route = './api/internal.php?object=centreon_configuration_poller&action=defaultValues'
    . '&target=resources&field=instance_id&id=' . $agent['poller_id'];
$attrPoller1 = array_merge(
    $attrPollers,
    array('defaultDatasetRoute' => $route)
);
$form->addElement(
    'select2',
    'poller_id',
    _('Linked Poller'),
    array(),
    $attrPoller1
);
$form->addElement('header', 'information', _("Basic Information"));
$form->addElement('text', 'token', _("Centreon SaaS Platform token"), $attrsTextURL);

/*
 * Forward information to SaaS
 */
$form->addElement('header', 'forward', _("Forward information"));
$form->addElement('text', 'gateway_ip', _("Gateway IP address"), $attrsTextURL);
$useGateway[] = $form->createElement('radio', 'use_gateway', null, _("Yes"), '1');
$useGateway[] = $form->createElement('radio', 'use_gateway', null, _("No"), '0');
$form->addGroup($useGateway, 'use_gateway', _("Use Gateway"), '&nbsp;');
$form->setDefaults(array('use_gateway' => '0'));
$useProxy[] = $form->createElement('radio', 'use_proxy', null, _("Centreon proxy"), '0');
$useProxy[] = $form->createElement('radio', 'use_proxy', null, _("Custom"), '1');
$useProxy[] = $form->createElement('radio', 'use_proxy', null, _("No"), '2');
$form->addGroup($useProxy, 'use_proxy', _("Use proxy"), '&nbsp;');
$form->setDefaults(array('use_proxy' => '0'));
$form->addElement('text', 'custom_proxy', _("Custom proxy"), $attrsTextURL, 'proto://user:password@url:port');
$insecureProxy[] = $form->createElement('radio', 'insecure_ssl', null, _("Yes"), '1');
$insecureProxy[] = $form->createElement('radio', 'insecure_ssl', null, _("No"), '0');
$form->addGroup($insecureProxy, 'insecure_ssl', _("Enable SSL insecure"), '&nbsp;');
$form->setDefaults(array('insecure_ssl' => '0'));
/*
 * Is Gateway
 */
$form->addElement('header', 'gateway', _("Gateway configuration"));
$isGateway[] = $form->createElement('radio', 'is_gateway', null, _("Yes"), '1');
$isGateway[] = $form->createElement('radio', 'is_gateway', null, _("No"), '0');
$form->addGroup($isGateway, 'is_gateway', _("Is Gateway"), '&nbsp;');
$form->setDefaults(array('is_gateway' => '0'));
$form->addElement('text', 'listening_port', _("Listing port"), $attrsText2);

/*
 * Additional Information
 */
$form->addElement('header', 'additional', _("Additional Information"));
$statusMode[] = $form->createElement('radio', 'activate', null, _("Enabled"), '1');
$statusMode[] = $form->createElement('radio', 'activate', null, _("Disabled"), '0');
$form->addGroup($statusMode, 'activate', _("Status"), '&nbsp;');
$form->setDefaults(array('activate' => '1'));

// Smarty template Init
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

if ($o == "c") {
    // Modify a service information
    $subC = $form->addElement('submit', 'submitC', _("Save"), array("class" => "btc bt_success"));
    $res = $form->addElement(
        'button',
        'reset',
        _("Reset"),
        array("onClick" => "history.go(0);", "class" => "btc bt_default")
    );
} elseif ($o == "a") {
    // Add a service information
    $subA = $form->addElement('submit', 'submitA', _("Save"), array("class" => "btc bt_success"));
    $res = $form->addElement('reset', 'reset', _("Reset"), array("class" => "btc bt_default"));
}
$form->setDefaults($agent);

$form->addElement('hidden', 'agent_id');
$page = $form->addElement('hidden', 'p');
$page->setValue($p);
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$valid = false;
if ($form->validate()) {
    if ($form->getSubmitValue("submitA")) {
        insertAgentInDB();
    } elseif ($form->getSubmitValue("submitC")) {
        updateAgentInDB();
    }
    $valid = true;
}

if ($valid) {
    include_once __DIR__ . "/listAgent.php";
} else {
    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->assign('centreon_path', $centreon->optGen['oreon_path']);

    $tpl->display(__DIR__ . "/formAgent.ihtml");
}
?>
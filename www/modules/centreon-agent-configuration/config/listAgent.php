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

require_once __DIR__ . "/DB-Func.php";
require_once __DIR__ . "/../../../class/centreonUtils.class.php";

if (!isset($_SESSION['centreon'])) {
    exit();
}

/*
 * Control filter
 */
$searchP = filter_var(
    $_POST['searchP'] ?? $_GET['searchP'] ?? null,
    FILTER_SANITIZE_STRING
);

if (isset($_POST['searchP']) || isset($_GET['searchP'])) {
    //saving filters values
    $centreon->historySearch[$url] = array();
    $centreon->historySearch[$url]['searchP'] = $searchP;
} else {
    //restoring saved values
    $searchP = $centreon->historySearch[$url]['searchP'] ?? null;
}

/*
 * Poller list
 */
$pollers = [];
$pollersQuery =  "SELECT id, name FROM nagios_server"
    . (isset($searchP) ? " WHERE name LIKE '" . $pearDB->escape($searchP) . "'" : '');
$dbResult = $pearDB->query($pollersQuery);
while ($row = $dbResult->fetch()) {
    $pollers[$row['id']] = $row['name'];
}

// Select configuration of agents from centreon.options table
$searchAgentList = '';
foreach ($pollers as $id => $name) {
    $searchAgentList .= "'config_agent_" . $id  . "',";
}

$searchAgentlistsTring = '';
if ($searchAgentList) {
    $searchAgentlistsTring = "IN (" . rtrim($searchAgentList, ",") . ")";
} else {
    $searchAgentlistsTring = "LIKE 'config_agent\_%'";
}
$agentsList = [];
if ($pollers) {
    $dbResult = $pearDB->query("SELECT * FROM options WHERE options.key " . $searchAgentlistsTring);
    while ($row = $dbResult->fetch()) {
        $agentsList[] = json_decode($row['value'], true);
    }
}

$rows = 0;

//Smarty template Init
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

// start header menu
$tpl->assign("headerMenu_name", _("Poller"));
$tpl->assign("headerMenu_activate", _("Status"));

$tpl->assign(
    'msg',
    array(
        "addL" => "main.php?p=" . $p . "&o=a",
        "addT" => _("Add"),
        "delConfirm" => _("Do you confirm the deletion ?")
    )
);

$form = new HTML_QuickFormCustom('select_form', 'POST', "?p=" . $p);

/*
 * Data preparation for listing 
 */
$style = "one";
$i = 0;
foreach ($agentsList as $agent) {
    $selectedElements = $form->addElement('checkbox', "select[" . $agent['id'] . "]");
    $elemArr[$i] = array(
        "MenuClass" => "list_" . $style,
        "RowMenu_select" => $selectedElements->toHtml(),
        "RowMenu_name" => CentreonUtils::escapeSecure($pollers[$agent['id']]),
        "RowMenu_link" => "main.php?p=60920&o=c&agent_id=" . $agent['id'],
        "RowMenu_activate" => $agent['activate'] ? _("Enabled") : _("Disabled"),
        "RowMenu_badgeactivate" => $agent['activate'] ? "service_ok" : "service_critical",
    );
    
    $style != "two" ? $style = "two" : $style = "one";
    $i++;
}

// Toolbar select
?>
    <script type="text/javascript">
        function setO(_i) {
            document.forms['form'].elements['o'].value = _i;
        }
    </script>
<?php
foreach (array('o1', 'o2') as $option) {
    $attrs1 = array(
        'onchange' => "javascript: " .
            " var bChecked = isChecked(); " .
            " if (this.form.elements['" . $option . "'].selectedIndex != 0 && !bChecked) {" .
            " alert('" . _("Please select one or more items") . "'); return false;} " .
            "if (this.form.elements['" . $option . "'].selectedIndex == 1 && confirm('" .
            _("Do you confirm the deletion ?") . "')) {" .
            " 	setO(this.form.elements['" . $option . "'].value); submit();} " .
            "else if (this.form.elements['" . $option . "'].selectedIndex == 2 || this.form.elements['" .
            $option . "'].selectedIndex == 3 ){" .
            " 	setO(this.form.elements['" . $option . "'].value); submit();} " .
            "this.form.elements['" . $option . "'].selectedIndex = 0"
    );
    $form->addElement(
        'select',
        $option,
        null,
        array(
            null => _("More actions..."),
            "d" => _("Delete"),
            "ms" => _("Enable"),
            "mu" => _("Disable"),
        ),
        $attrs1
    );

    $o1 = $form->getElement($option);
    $o1->setValue(null);
}

$tpl->assign("elemArr", $elemArr);
$tpl->assign('limit', $limit);

$attrBtnSuccess = array(
    "class" => "btc bt_success",
    "onClick" => "window.history.replaceState('', '', '?p=" . $p . "');"
);
$form->addElement('submit', 'Search', _("Search"), $attrBtnSuccess);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display(__DIR__ . "/listAgent.ihtml");
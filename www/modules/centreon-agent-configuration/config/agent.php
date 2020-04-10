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

require_once "./include/common/common-Func.php";
require_once __DIR__ . "/DB-Func.php";

$agentId = filter_var(
    $_GET["agent_id"] ?? $_POST["agent_id"] ?? 0,
    FILTER_VALIDATE_INT
);
$select = $_GET["select"] ?? $_POST["select"] ?? array();
$dupNbr = filter_var(
    $_GET["dupNbr"] ?? $_POST["dupNbr"] ?? 0,
    FILTER_VALIDATE_INT
);

// Check options
if (isset($_POST["o1"]) && isset($_POST["o2"])) {
    if ($_POST["o1"] != "") {
        $o = $_POST["o1"];
    }
    if ($_POST["o2"] != "") {
        $o = $_POST["o2"];
    }
}

$acl = $centreon->user->access;
$aclDbName = $acl->getNameDBAcl();

/* Set the real page */
if ($ret['topology_page'] != "" && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

switch ($o) {
    case "a": //Add an agent
    case "c": //Modify an agent
        require_once($path."formAgent.php");
        break;
    case "s": //Activate an agent
        enableAgentInDB($agentId, true);
        require_once($path."listAgent.php");
        break;
    case "u": //Desactivate an agent
        enableAgentInDB($agentId, false);
        require_once($path."listAgent.php");
        break;
    case "d": //Delete n agents
        deleteAgentInDB(isset($select) ? $select : array());
        require_once($path."listAgent.php");
        break;
    default:
        require_once($path."listAgent.php");
        break;
}

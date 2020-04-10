<?php

/**
 * Insert Centeron Agent Configuration in DB
 */
function insertAgentInDB($ret = array()): void
{
    global $form, $pearDB;

    $temp = $form->getSubmitValues();
 
    $data['id'] = $temp['poller_id'];
    $data['token'] = $temp['token'];
    $data['use_proxy'] = $temp['use_proxy']['use_proxy'];
    $data['custom_proxy'] = $temp['custom_proxy'];
    $data['no_ssl'] = $temp['insecure_ssl']['insecure_ssl'];
    $data['is_gateway'] = $temp['is_gateway']['is_gateway'];
    $data['use_gateway'] = $temp['use_gateway']['use_gateway'];
    $data['listen'] = $temp['listening_port'];
    $data['activate'] = $temp['activate']['activate'];
    
    $pearDB->query("DELETE FROM options WHERE options.key = 'config_agent_" . (int)$data['id'] . "'");
    $pearDB->query("INSERT INTO options VALUES('config_agent_" . (int)$data['id'] . "', '" . json_encode($data) . "')");
}

/**
 * Update Centeron Agent Configuration in DB
 */
function updateAgentInDB()
{
    insertAgentInDB();
}

/**
 * Delete Centeron Agent Configuration in DB
 */
function deleteAgentInDB(int $agentId) {
    global $pearDB;

    $pearDB->query("DELETE FROM options WHERE options.key = 'config_agent_" . (int)$agentId . "'");
}

/**
 * Enable Centeron Agent Configuration in DB
 */
function enableAgentInDB(int $agentId, bool $enable = false) {
    global $pearDB;

    $dbResult = $pearDB->query(
        "SELECT options.value FROM options WHERE options.key = 'config_agent_" . (int)$agentId . "' LIMIT 1"
    );
    $agent = json_decode($dbResult->fetch()['value'], true);

    if ($enable) {
        $agent['activate']['activate'] = 1;
    } else {
        $agent['activate']['activate'] = 0;
    }

    $pearDB->query("DELETE FROM options WHERE options.key = 'config_agent_" . (int)$agentId . "'");
    $pearDB->query("INSERT INTO options VALUES('config_agent_" . (int)$agentId . "', '" . json_encode($agent) . "')");
}
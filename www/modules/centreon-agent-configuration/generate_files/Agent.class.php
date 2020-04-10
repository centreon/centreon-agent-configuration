<?php
/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

use Symfony\Component\Yaml\Yaml;

class Agent extends AbstractObject
{
    /**
     * Generate anomaly configuration for a poller
     *
     * @param integer $pollerId
     * @param integer $localhost poller is localhost or not
     * @return void
     */
    public function generateFromPollerId(int $pollerId, ?int $localhost) : void
    {
        $data = $this->_generateAgentConfiguration($pollerId);
        
        $this->_sendgorgoneCommand($data, $pollerId);
    }
    /**
     * Generate Agent configuration
     *
     * @param int $id The ID of the poller
     *
     * @return array
     */
    private function _generateAgentConfiguration(int $id) : array
    {
        /*
         * Get Configred values
         */
        $stmt = $this->backend_instance->db->query(
            "SELECT options.value FROM options WHERE options.key = 'config_agent_" . (int)$id . "' LIMIT 1"
        );
        $stmt->execute();
        $agent = json_decode($stmt->fetch()['value'], true);

        /*
         * General configuration
         */
        $data = [
            'cmaas' => [
                'token' => $agent['token'],
                'send_interval' => '5m',
                'send_max_events' => 1000
            ],
            'logger' => [
                'level' => 'error',
                'filename' => '/var/log/centreon-agent/centreon-agent.log'
            ]
        ];

        // Add collect agent configuration
        $server_type = $this->_serverType($id);
        $data = array_merge_recursive($data, $this->_getCollectValues($id, $server_type));

        // Add gateway definition
        $data = array_merge_recursive($data, $this->_getGatewayConfiguration($agent));

        // Add proxy configuration
        $data = array_merge_recursive($data, $this->_getProxyconfiguration($agent));

        return $data;
    }

    /**
     * Reset variables
     *
     * @param  void
     * @return void
     */
    public function reset() : void
    {
    }

    /**
     * Get server type
     *
     * @param int $id The ID of the poller
     *
     * @return string The type of poller
     */
    private function _serverType(int $id) : string
    {
        $stmt = $this->backend_instance->db->prepare(
            "SELECT * FROM `nagios_server` WHERE `id` = :server_id LIMIT 1"
        );
        $stmt->bindParam(':server_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $cfg_server = array_map("myDecode", $stmt->fetchRow());

        $stmt = $this->backend_instance->db->prepare(
            "SELECT ip FROM remote_servers"
        );
        $stmt->execute();
        $remotesServerIPs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($cfg_server['localhost']) {
            $serverType = "central";
        } elseif (in_array($cfg_server['ns_ip_address'], $remotesServerIPs)) {
            $serverType = "remote";
        } else {
            $serverType = "poller";
        }

        return $serverType;
    }

    /**
     * Get collect values
     *
     * @param int    $id         The ID of the poller
     * @param string $pollerType The type of poller
     *
     * @return array The collect values for the agent configuration
     */
    private function _getCollectValues(int $id, string $pollerType) : array
    {
        $data = [
            'collect' => [
                'interval' => '5m',
                'system' => true,
            ]
        ];

        // Get centreonengine_stats_file
        $stmt = $this->backend_instance->db->prepare(
            "SELECT status_file FROM cfg_nagios WHERE nagios_id = :server_id LIMIT 1"
        );
        $stmt->bindParam(':server_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $cfg_server = array_map("myDecode", $stmt->fetchRow());

        $data['collect']['centreon']['centreonengine_stats_file'] = $cfg_server['status_file'];

        // Get centreonbroker_stats_files
        $stmt = $this->backend_instance->db->prepare(
            "SELECT CONCAT(cache_directory, '/', config_filename) AS config
            FROM cfg_centreonbroker
            WHERE ns_nagios_server = :server_id"
        );
        $stmt->bindParam(':server_id', $id, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $data['collect']['centreon']['centreonbroker_stats_files'][] = $row['config'];
        }

        // Get Centeon DB access
        if (strcmp($pollerType, 'central') == 0) {
            include _CENTREON_ETC_ . '/centreon.conf.php';

            $data['collect']['centreon']['centreonweb'] = [
                'config_dsn' =>  $conf_centreon['user'] . ':' . $conf_centreon['password'] . '@/' . $conf_centreon['db'],
                'storage_dsn' => $conf_centreon['user'] . ':' . $conf_centreon['password'] . '@/' . $conf_centreon['dbcstg']
            ];
        } elseif (strcmp($pollerType, 'remote') == 0) {
            $stmt = $this->backend_instance->db->prepare(
                "SELECT config_key, config_value
                FROM cfg_centreonbroker_info AS cbi
                INNER JOIN cfg_centreonbroker AS cb ON (cb.config_id = cbi.config_id)
                WHERE config_key IN ('db_user', 'db_password')
                AND ns_nagios_server = :server_id"
            );
            $stmt->bindParam(':server_id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $db_user = '';
            $db_password = '';

            while ($row = $stmt->fetch()) {
                if (strcmp($row['config_key'], 'db_user') == 0) {
                    $db_user = $row['config_value'];
                } elseif (strcmp($row['config_key'], 'db_password') == 0) {
                    $db_password = $row['config_value'];
                }
            }

            $data['collect']['centreonweb'] = [
                'config_dsn' =>  $conf_centreon['user'] . ':' . $conf_centreon['password'] . '@/' . $conf_centreon['db'],
                'storage_dsn' => $conf_centreon['user'] . ':' . $conf_centreon['password'] . '@/' . $conf_centreon['dbcstg']
            ];
        }

        // Get map configuration
        if (strcmp($pollerType, 'central') == 0 || strcmp($pollerType, 'remote') == 0) {
            $stmt = $this->backend_instance->db->prepare(
                "SELECT options.value FROM options WHERE options.key = 'map_light_server_address'"
            );
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row['value']) {
                $data['collect']['centreonmap'] = [
                    'url' => $row['value'] . '/jolokia'
                ];
            }
        }

        return $data;
    }

    /**
     * Get Gateway configuration
     *
     * @param array  $agent      The agent configuration
     *
     * @return array The configuration of the Gateway
     */
    private function _getGatewayConfiguration(array $agent): array
    {
        $data = [];

        if (isset($agent['use_gateway']) && $agent['use_gateway'] == 1 && isset($agent['gateway_ip'])
            && (filter_var($agent['gateway_ip'], FILTER_VALIDATE_IP)
            || filter_var($agent['gateway_ip'], FILTER_VALIDATE_DOMAIN))
        ) {
            $data['cmaas']['gateway'] = [
                'url' => 'htt://' . $agent['gateway_ip']
    
            ];
        }

        if (isset($agent['is_gateway']) && $agent['is_gateway'] == 1
            && isset($agent['listen']) && is_int(isset($agent['listen']))
        ) {
            $data['cmaas']['gateway'] = [
                'enable' => true,
                'listen_port' => (int)$agent['listen']
    
            ];
        }
        
        return $data;
    }

    /**
     * Get Gateway proxy configuration
     *
     * @param array  $agent      The agent configuration
     *
     * @return array The configuration of the Gateway
     */
    private function _getProxyconfiguration(array $agent): array
    {
        $data = [];

        if (isset($agent['use_proxy'])) {
            if ($agent['use_proxy'] == 0) {
                // Use Centreon proxy
                $stmt = $this->backend_instance->db->prepare(
                    "SELECT * FROM options
                    WHERE options.key IN ('proxy_url', 'proxy_port', 'proxy_user', 'proxy_password')"
                );
                $stmt->execute();
        
                $proxy_url = '';
                $proxy_port = '';
                $proxy_user = '';
                $proxy_password = '';
                while ($row = $stmt->fetch()) {
                    if (strcmp($row['key'], 'proxy_url') == 0) {
                        $proxy_url = $row['value'];
                    } elseif (strcmp($row['key'], 'proxy_port') == 0) {
                        $proxy_port = $row['value'];
                    } elseif (strcmp($row['key'], 'proxy_user') == 0) {
                        $proxy_user = $row['value'];
                    } elseif (strcmp($row['key'], 'proxy_password') == 0) {
                        $proxy_password = $row['value'];
                    }

                    $proxy = '';
                    $configuredProxy = parse_url($proxy_url);
                    if (!isset($configuredProxy["scheme"])) {
                        $proxy = 'http://';
                    } else {
                        $proxy = $configuredProxy["scheme"] . '://';
                    }

                    if (isset($proxy_user) && isset($proxy_password)) {
                        $proxy .= $proxy_user . ':' . $proxy_password . '@';
                    }

                    $proxy .= $proxy_url;

                    if (isset($proxy_port)) {
                        $proxy .= ':' . $proxy_port;
                    }

                    $proxy_ssl_insecure = (isset($agent['insecure_ssl']) && $agent['insecure_ssl'] == 1 ? true : false);

                    $data['cmaas'] = [
                        'gateway' => [
                            'proxy_url' => $proxy,
                            'proxy_ssl_insecure' => $proxy_ssl_insecure
                        ]
                    ];
                }
            } elseif ($agent['use_proxy'] == 1
                && isset($agent['custom_proxy'])
            ) {
                $proxy = '';
                $customProxy = parse_url($agent['custom_proxy']);
                if (!isset($customProxy["scheme"])) {
                    $proxy = 'http://';
                } else {
                    $proxy = $customProxy["scheme"] . '://';
                }

                if (isset($customProxy["user"]) && isset($customProxy["pass"])) {
                    $proxy .= $customProxy["user"] . ':' . $customProxy["pass"] . '@';
                }

                $proxy .= $customProxy["host"];

                if (isset($customProxy["port"])) {
                    $proxy .= ':' . $customProxy["port"];
                }

                $proxy_ssl_insecure = (isset($agent['no_ssl']) && $agent['no_ssl'] == 1 ? true : false);

                $data['cmaas']['gateway'] = [
                    'proxy_url' => $proxy,
                    'proxy_ssl_insecure' => $proxy_ssl_insecure
                ];
            }
        }

        return $data;
 
    }

    /**
     * Get the Gorgone configuration
     *
     * @return array The listening port
     */
    private function _getGorgoneConfiguration(): array
    {
        $gorgone_api = [];

        $stmt = $this->backend_instance->db->prepare(
            "SELECT * FROM options WHERE options.key LIKE 'gorgone_api\_%'"
        );
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $gorgone_api[$row['key']] = $row['value'];
        }
        
        return $gorgone_api;
    }

    /**
     * Send command to gorgone
     *
     * @param array $data The data to send
     * @param int   $id   The ID of the poller
     *
     * @return void
     */
    private function _sendgorgoneCommand(array $data, int $id): void
    {
        // Encode data
        $yaml = Yaml::dump($data, 4, 4);
        $encodedString = base64_encode($yaml);

        // Prepare command
        $command = [
            "command" => 'echo -n ' . $encodedString . ' | base64 -d > "/etc/centreon-agent/centreon-agent.yml"'
        ];

        // Get gorgone listening port
        $gorgone_api = $this->_getGorgoneConfiguration();

        // Send command to generate configuraton to Gorgone
        $url = $gorgone_api['gorgone_api_ssl'] ? 'https://' : 'http://';
        $url .= $gorgone_api['gorgone_api_address'] . ':' . $gorgone_api['gorgone_api_port'];
        $url .= '/api/nodes/' . $id . '/core/action/command';

        $restHttp = new CentreonRestHttp();
        $returnData = $restHttp->call(
            $url,
            'POST',
            [$command],
            [
                "Accept: application/json",
                "Content-Type: application/json"
            ],
            false,
            false,
            true
        );

        // Send command to generate configuraton to Gorgone
        $command = [
            "command" => 'systemctl restart centreon-agent'
        ];

        $restHttp = new CentreonRestHttp();
        $returnData = $restHttp->call(
            $url,
            'POST',
            [$command],
            [
                "Accept: application/json",
                "Content-Type: application/json"
            ],
            false,
            false,
            true
        );
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class MobileAssistantConnector
{
    const PLUGIN_CODE = '2';
    const PLUGIN_VERSION = '1.0.2';

    public $call_function;
    public $hash;
    protected $sDBHost = '';
    protected $sDBUser = '';
    protected $sDBPwd = '';
    protected $sDBName = '';
    protected $sDBPrefix = '';
    protected $site_url = '';
    protected $CartType = -1;
    protected $status_list_hide = array("auto-draft", "draft", "trash" );


    public function __construct() {
        if (!ini_get('date.timezone') || ini_get('date.timezone' == "")) {
            @date_default_timezone_set(@date_default_timezone_get());
        }

        $this->check_is_woo_activated();

        if (isset($_REQUEST['call_function'])) {
            $this->call_function = $this->validate_type($_REQUEST['call_function'], 'STR');
        }
        if (isset($_REQUEST['hash'])) {
            $this->hash = $this->validate_type($_REQUEST['hash'], 'STR');
        }

        if (empty($this->call_function)) {
            $this->run_self_test();
        }

        if (!$this->check_auth()) {
            $this->generate_output('auth_error');
        }

        if ($this->call_function == 'test_config') {
            $this->generate_output(array('test' => 1));
        }

        $params = $this->validate_types($_REQUEST, array(
            'show' => 'INT',
            'page' => 'INT',
            'search_order_id' => 'STR',
            'orders_from' => 'STR',
            'orders_to' => 'STR',
            'customers_from' => 'STR',
            'customers_to' => 'STR',
            'date_from' => 'STR',
            'date_to' => 'STR',
            'graph_from' => 'STR',
            'graph_to' => 'STR',
            'stats_from' => 'STR',
            'stats_to' => 'STR',
            'products_to' => 'STR',
            'products_from' => 'STR',
            'order_id' => 'INT',
            'user_id' => 'INT',
            'params' => 'STR',
            'val' => 'STR',
            'search_val' => 'STR',
            'statuses' => 'STR',
            'sort_by' => 'STR',
            'last_order_id' => 'STR',
            'product_id' => 'INT',
            'get_statuses' => 'INT',
            'cust_with_orders' => 'INT',
            'data_for_widget' => 'INT',
            'registration_id' => 'STR',
            'registration_id_old' => 'STR',
            'api_key' => 'STR',
            'push_new_order' => 'INT',
            'push_order_statuses' => 'STR',
            'push_new_customer' => 'INT',
            'app_connection_id' => 'STR',
            'push_currency_code' => 'STR',
            'action' => 'STR',
            'carrier_code' => 'STR',
            'custom_period' => 'INT',
            'store_id' => 'STR',
            'new_status' => 'STR',
            'notify_customer' => 'INT',
            'currency_code' => 'STR',
            'fc' => 'STR',
            'module' => 'STR',
            'controller' => 'STR',
            'change_order_status_comment' => 'STR',
        ));

        foreach ($params as $k => $value) {
            $this->{$k} = $value;
        }


//        if(empty($this->currency_code) || $this->currency_code == 'not_set') {
//            $this->currency = '';
//
//        } else if($this->currency_code == 'base_currency') {
            $this->currency = get_woocommerce_currency();

//        } else {
//            $this->currency = $this->currency_code;
//        }

        if(empty($this->push_currency_code) || $this->push_currency_code == 'not_set') {
            $this->push_currency_code = '';
        }

        $this->site_url = get_site_url();
    }

    private function check_is_woo_activated() {
        if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            $this->generate_output('module_disabled');
        }
    }

    private function run_self_test() {
        global $woocommerce;

        $html = '<h2>Mobile Assistant Connector Self Test Tool</h2>'
            . '<div style="padding: 5px; margin: 10px 0;">This tool checks your website to make sure there are no issues in your hosting configuration.<br />Your hosting support can solve all issues found here.</div><br/>'
            . '<table cellpadding=4><tr><th>Test Title</th><th>Result</th></tr>'
            . '<tr><td>Module Version</td><td>' . self::PLUGIN_VERSION . '</td><td></td></tr>'
            . '<tr><td>WordPress Version</td><td>' . get_bloginfo('version') . '</td><td></td></tr>'
            . '<tr><td>WooCommerce Version</td><td>' . $woocommerce->version . '</td><td></td></tr>'
            . '<tr><td>Default Login and Password Changed</td><td>'
            . (( $res = $this->test_default_password_is_changed() ) ? '<span style="color: #008000;">Yes</span>' : '<span style="color: #ff0000;">Fail</span>') . '</td>';

        if(!$res) {
            $html .= '<td>Change your login credentials in Mobile Assistant Connector to make your connection secure</td>';
        }

        $html .= '</table><br/>'
            . '<div style="margin-top: 15px; font-size: 13px;">Mobile Assistant Connector by <a href="http://emagicone.com" target="_blank" style="color: #15428B">eMagicOne</a></div>';

        die($html);
    }

    private function test_default_password_is_changed() {
        $options = get_option('mobassistantconnector');

        return !($options['login'] == '1' && md5($options['pass']) == 'c4ca4238a0b923820dcc509a6f75849b');
    }

    public function generate_output($data) {
        //global $version;
        $add_connector_version = false;
        if (in_array($this->call_function, array("test_config", "get_store_title", "get_store_stats", "get_data_graphs"))) {
            if (is_array($data) && $data != 'auth_error' && $data != 'connection_error' && $data != 'old_module') {
                $add_connector_version = true;
            }
        }

        function reset_null(&$item, $key) {
            if (empty($item) && $item != 0) {
                $item = '';
            }
            if (!is_array($item) && !is_object($item)) {
                $item = trim($item);
            }
        }

        if (!is_array($data)) {
            $data = array($data);
        }

        if (is_array($data)) {
            array_walk_recursive($data, 'reset_null');
        }

        if ($add_connector_version) {
            $data['module_version'] = self::PLUGIN_CODE;
        }

        if(!function_exists('json_encode')) {
            $data = to_json($data);
        } else {
            $data = json_encode($data);
        }

        //header('Content-Type: text/javascript;charset=utf-8');
        die($data);
    }


    public function my_json_encode($data) {
        if (is_array($data) || is_object($data)) {
            $islist = is_array($data) && (empty($data) || array_keys($data) === range(0, count($data) - 1));

            if ($islist) {
                $json = '[' . implode(',', array_map('my_json_encode', $data)) . ']';
            } else {
                $items = Array();
                foreach ($data as $key => $value) {
                    $items[] = $this->my_json_encode("$key") . ':' . $this->my_json_encode($value);
                }
                $json = '{' . implode(',', $items) . '}';
            }
        } elseif (is_string($data)) {
            # Escape non-printable or Non-ASCII characters.
            $string = '"' . addcslashes($data, "\\\"\n\r\t/" . chr(8) . chr(12)) . '"';
            $json = '';
            $len = strlen($string);
            # Convert UTF-8 to Hexadecimal Codepoints.
            for ($i = 0; $i < $len; $i++) {

                $char = $string[$i];
                $c1 = ord($char);

                # Single byte;
                if ($c1 < 128) {
                    $json .= ($c1 > 31) ? $char : sprintf("\\u%04x", $c1);
                    continue;
                }

                # Double byte
                $c2 = ord($string[++$i]);
                if (($c1 & 32) === 0) {
                    $json .= sprintf("\\u%04x", ($c1 - 192) * 64 + $c2 - 128);
                    continue;
                }

                # Triple
                $c3 = ord($string[++$i]);
                if (($c1 & 16) === 0) {
                    $json .= sprintf("\\u%04x", (($c1 - 224) << 12) + (($c2 - 128) << 6) + ($c3 - 128));
                    continue;
                }

                # Quadruple
                $c4 = ord($string[++$i]);
                if (($c1 & 8) === 0) {
                    $u = (($c1 & 15) << 2) + (($c2 >> 4) & 3) - 1;

                    $w1 = (54 << 10) + ($u << 6) + (($c2 & 15) << 2) + (($c3 >> 4) & 3);
                    $w2 = (55 << 10) + (($c3 & 15) << 6) + ($c4 - 128);
                    $json .= sprintf("\\u%04x\\u%04x", $w1, $w2);
                }
            }
        } else {
            # int, floats, bools, null
            $json = strtolower(var_export($data, true));
        }
        return $json;
    }


    private function check_auth()
    {
        $options = get_option('mobassistantconnector');

        if (isset($this->hash) && md5($options['login'] . $options['pass']) == $this->hash) {
            return true;
        }

        return false;
    }


    protected function validate_types($array, $names)
    {
        foreach ($names as $name => $type) {
            if (isset($array["$name"])) {
                switch ($type) {
                    case 'INT':
                        $array["$name"] = intval($array["$name"]);
                        break;
                    case 'FLOAT':
                        $array["$name"] = floatval($array["$name"]);
                        break;
                    case 'STR':
                        $array["$name"] = str_replace(array("\r", "\n"), ' ', addslashes(htmlspecialchars(trim(urldecode($array["$name"])))));
                        break;
                    case 'STR_HTML':
                        $array["$name"] = addslashes(trim(urldecode($array["$name"])));
                        break;
                    default:
                        $array["$name"] = '';
                }
            } else {
                $array["$name"] = '';
            }
        }
        return $array;
    }

    protected function validate_type($value, $type)
    {
        switch ($type) {
            case 'INT':
                $value = intval($value);
                break;
            case 'FLOAT':
                $value = floatval($value);
                break;
            case 'STR':
                $value = str_replace(array("\r", "\n"), ' ', addslashes(htmlspecialchars(trim($value))));
                break;
            case 'STR_HTML':
                $value = addslashes(trim($value));
                break;
            default:
        }
        return $value;
    }

    //for address
    protected function split_values($arr, $keys, $sign = ', ')
    {
        $new_arr = array();
        foreach ($keys as $key) {
            if (isset($arr[$key])) {
                if (!is_null($arr[$key]) && $arr[$key] != '') {
                    $new_arr[] = $arr[$key];
                }
            }
        }
        return implode($sign, $new_arr);
    }

    protected function get_custom_period($period)
    {
        $custom_period = array('start_date' => "", 'end_date' => "");
        $format = "m/d/Y";

        switch ($period) {
            case 0: //3 days
                $custom_period['start_date'] = date($format, mktime(0, 0, 0, date("m"), date("d") - 2, date("Y")));
                $custom_period['end_date'] = date($format, mktime(23, 59, 59, date("m"), date("d"), date("Y")));
                break;

            case 1: //7 days
                $custom_period['start_date'] = date($format, mktime(0, 0, 0, date("m"), date("d") - 6, date("Y")));
                $custom_period['end_date'] = date($format, mktime(23, 59, 59, date("m"), date("d"), date("Y")));
                break;

            case 2: //Prev week
                $custom_period['start_date'] = date($format, mktime(0, 0, 0, date("n"), date("j") - 6, date("Y")) - ((date("N")) * 3600 * 24));
                $custom_period['end_date'] = date($format, mktime(23, 59, 59, date("n"), date("j"), date("Y")) - ((date("N")) * 3600 * 24));
                break;

            case 3: //Prev month
                $custom_period['start_date'] = date($format, mktime(0, 0, 0, date("m") - 1, 1, date("Y")));
                $custom_period['end_date'] = date($format, mktime(23, 59, 59, date("m"), date("d") - date("j"), date("Y")));
                break;

            case 4: //This quarter
                $m = date("n");
                $start_m = 1;
                $end_m = 3;

                if ($m <= 3) {
                    $start_m = 1;
                    $end_m = 3;
                } else if ($m >= 4 && $m <= 6) {
                    $start_m = 4;
                    $end_m = 6;
                } else if ($m >= 7 && $m <= 9) {
                    $start_m = 7;
                    $end_m = 9;
                } else if ($m >= 10) {
                    $start_m = 10;
                    $end_m = 12;
                }

                $custom_period['start_date'] = date($format, mktime(0, 0, 0, $start_m, 1, date("Y")));
                $custom_period['end_date'] = date($format, mktime(23, 59, 59, $end_m + 1, date(1) - 1, date("Y")));
                break;

            case 5: //This year
                $custom_period['start_date'] = date($format, mktime(0, 0, 0, date(1), date(1), date("Y")));
                $custom_period['end_date'] = date($format, mktime(23, 59, 59, date(1), date(1) - 1, date("Y") + 1));
                break;

            case 6: //Last year
                $custom_period['start_date'] = date($format, mktime(0, 0, 0, date(1), date(1), date("Y")-1));
                $custom_period['end_date'] = date($format, mktime(23, 59, 59, date(1), date(1)-1, date("Y")));
                break;
        }

        return $custom_period;
    }

        private function get_filter_statuses($statuses)
    {
        $statuses = explode("|", $statuses);
        if (!empty($statuses)) {
            $stat = array();
            foreach ($statuses as $status) {
                if ($status != "") {
                    $stat[] = $status;
                }
            }
            $parse_statuses = implode("','", $stat);
            return $parse_statuses;
        }

        return $statuses;
    }


    public function get_currencies() {
        $all_currencies = array();

        $currency_code_options = get_woocommerce_currencies();

        foreach ($currency_code_options as $code => $name) {
            $all_currencies[] = array('code' => $code, 'name' => $name);
        }

        return $all_currencies;
    }


    public function get_store_title() {
        $title = get_option('blogname');

        return array('test' => 1, 'title' => $title);
    }


    public function get_store_stats() {
        $data_graphs = '';
        $order_status_stats = array();
        $store_stats = array('count_orders' => "0", 'total_sales' => "0", 'count_customers' => "0", 'count_products' => "0", "last_order_id" => "0", "new_orders" => "0");
        $today = date("Y-m-d", time(0));
        $date_from = $date_to = $today;

        $data = array();

        if (!empty($this->stats_from)) {
            $date_from = $this->stats_from;
        }

        if (!empty($this->stats_to)) {
            $date_to = $this->stats_to;
        }

        if (!empty($this->custom_period) && strlen($this->custom_period) > 0) {
            $custom_period = $this->get_custom_period($this->custom_period);

            $date_from = $custom_period['start_date'];
            $date_to = $custom_period['end_date'];
        }

        if (!empty($date_from)) {
            $data['date_from'] = $date_from . " 00:00:00";
        }

        if (!empty($date_to)) {
            $data['date_to'] = $date_to . " 23:59:59";
        }

        if (!empty($this->statuses)) {
            $data['statuses'] = $this->get_filter_statuses($this->statuses);
        }

        $orders_stats = $this->_get_total_orders_i_products($data);
        $store_stats = array_merge($store_stats, $orders_stats);

        $customers_stats = $this->_get_total_customers($data);
        $store_stats = array_merge($store_stats, $customers_stats);


        if (!isset($this->data_for_widget) || empty($this->data_for_widget) || $this->data_for_widget != 1) {
            $data_graphs = $this->get_data_graphs();
            $order_status_stats = $this->get_status_stats();
        }

        $result = array_merge($store_stats, array('data_graphs' => $data_graphs), array('order_status_stats' => $order_status_stats));

        return $result;
    }


    public function get_data_graphs() {
        global $wpdb;

        $orders = array();
        $customers = array();
        $average = array('avg_sum_orders' => 0, 'avg_orders' => 0, 'avg_customers' => 0, 'avg_cust_order' => '0.00', 'tot_orders' => 0, 'sum_orders' => '0.00', 'tot_customers' => 0, 'currency_symbol' => "");


        if (empty($this->graph_from)) {
            $this->graph_from = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d")-7, date("Y")));
        }
        $startDate = $this->graph_from . " 00:00:00";

        if (empty($this->graph_to)) {
            if(!empty($this->stats_to)) {
                $this->graph_to = $this->stats_to;
            } else {
                $this->graph_to = date("Y-m-d", time());
            }
        }
        $endDate = $this->graph_to . " 23:59:59";


        if(!empty($this->custom_period) && strlen($this->custom_period) > 0) {
            $custom_period = $this->get_custom_period($this->custom_period);

            $startDate = $custom_period['start_date'];
            $endDate = $custom_period['end_date'];
        }


        $plus_date = "+1 day";
        $custom_period = $this->custom_period;
        if(isset($custom_period) && strlen($custom_period) > 0) {
            $custom_period_date = $this->get_custom_period($custom_period);

            if($custom_period == 3) {
                $plus_date = "+3 day";
            } else if($custom_period == 4) {
                $plus_date = "+1 week";
            } else if($custom_period == 5 || $custom_period == 6 || $custom_period == 7) {
                $plus_date = "+1 month";
            }

            if($custom_period == 7) {
                $sql = "SELECT MIN(post_date_gmt) AS min_date_add, MAX(post_date_gmt) AS max_date_add FROM `{$wpdb->posts}` WHERE post_type = 'shop_order'";
                if(!empty($this->status_list_hide)) {
                    $sql .= " AND post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
                }

                if($max_date = $wpdb->get_row($sql, ARRAY_A)) {
                    $startDate = $max_date['min_date_add'];
                    $endDate = $max_date['max_date_add'];
                }

            } else {
                $startDate = $custom_period_date['start_date'] . " 00:00:00";
                $endDate = $custom_period_date['end_date'] . " 23:59:59";
            }
        }

        $startDate = strtotime($startDate);
        $endDate = strtotime($endDate);

        $date = $startDate;
        $d = 0;
        while ($date <= $endDate) {
            $d++;
            $query = "SELECT COUNT(DISTINCT(posts.ID)) AS tot_orders, SUM(meta_order_total.meta_value) AS value
                    FROM `{$wpdb->posts}` AS posts";

            if(!function_exists('wc_get_order_status_name')) {
                $query .= " LEFT JOIN `{$wpdb->term_relationships}` AS order_status_terms ON order_status_terms.object_id = posts.ID
                                AND order_status_terms.term_taxonomy_id IN (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE taxonomy = 'shop_order_status')
                            LEFT JOIN `{$wpdb->terms}` AS status_terms ON status_terms.term_id = order_status_terms.term_taxonomy_id";
            }

            $query .= " LEFT JOIN `{$wpdb->postmeta}` AS meta_order_total ON meta_order_total.post_id = posts.ID AND meta_order_total.meta_key = '_order_total'
                        WHERE posts.post_type = 'shop_order'
                            AND UNIX_TIMESTAMP(posts.post_date_gmt) >= '%d' AND UNIX_TIMESTAMP(posts.post_date_gmt) < '%d' ";
            if(!empty($this->status_list_hide)) {
                $query .= " AND posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
            }

            $query = sprintf($query, $date, strtotime($plus_date, $date));

            if (!empty($this->statuses)) {
                if(function_exists('wc_get_order_status_name')) {
                    $query .= sprintf(" AND posts.post_status IN ('%s')", $this->get_filter_statuses($this->statuses));
                } else {
                    $query .= sprintf(" AND status_terms.slug IN ('%s')", $this->get_filter_statuses($this->statuses));
                }
            }

            $query .= " GROUP BY DATE(posts.post_date_gmt) ORDER BY posts.post_date_gmt";

            $total_order_per_day = 0;
            if($results = $wpdb->get_results($query, ARRAY_A)) {
                foreach ($results as $row) {
                    $total_order_per_day += $row['value'];

                    $average['tot_orders'] += $row['tot_orders'];
                    $average['sum_orders'] += $row['value'];
                }
            }

            $orders[] = array($date*1000, $total_order_per_day);


            $query = "SELECT COUNT(DISTINCT(c.ID)) AS tot_customers
                      FROM `{$wpdb->users}` AS c
                        LEFT JOIN `{$wpdb->usermeta}` AS cap ON cap.user_id = c.ID
				      WHERE cap.meta_key = 'wp_capabilities'
                        AND cap.meta_value LIKE '%%%s%%'
                        AND UNIX_TIMESTAMP(c.user_registered) >= '%d'
                        AND UNIX_TIMESTAMP(c.user_registered) < '%d'";
            $query = sprintf($query, 'customer', $date, strtotime($plus_date, $date));

            $query .= " GROUP BY DATE(c.user_registered) ORDER BY c.user_registered";

            $total_customer_per_day = 0;
            if($results = $wpdb->get_results($query, ARRAY_A)) {
                foreach ($results as $row) {
                    $total_customer_per_day += $row['tot_customers'];

                    $average['tot_customers'] += $row['tot_customers'];
                }
            }
            $customers[] = array($date*1000, $total_customer_per_day);

            $date = strtotime($plus_date, $date);
        }

        // Add 2 additional element into array of orders for graph in mobile application
        if (count($orders) == 1) {
            $orders_tmp = $orders[0];
            $orders = array();
            $orders[0][] = strtotime(date("Y-m-d", $orders_tmp[0] / 1000) . "-1 month") * 1000;
            $orders[0][] = 0;
            $orders[1] = $orders_tmp;
            $orders[2][] = strtotime(date("Y-m-d", $orders_tmp[0] / 1000) . "+1 month") * 1000;
            $orders[2][] = 0;
        }

        // Add 2 additional element into array of customers for graph in mobile application
        if (count($customers) == 1) {
            $customers_tmp = $customers[0];
            $customers = array();
            $customers[0][] = strtotime(date("Y-m-d", $customers_tmp[0] / 1000) . "-1 month") * 1000;
            $customers[0][] = 0;
            $customers[1] = $customers_tmp;
            $customers[2][] = strtotime(date("Y-m-d", $customers_tmp[0] / 1000) . "+1 month") * 1000;
            $customers[2][] = 0;
        }

        if ($d <= 0) $d = 1;
        $average['avg_sum_orders'] = number_format($average['sum_orders']/$d, 2, '.', ' ');
        $average['avg_orders'] = number_format($average['tot_orders']/$d, 1, '.', ' ');
        $average['avg_customers'] = number_format($average['tot_customers']/$d, 1, '.', ' ');

        if ($average['tot_customers'] > 0) {
            $average['avg_cust_order'] = number_format($average['sum_orders']/$average['tot_customers'], 1, '.', ' ');
        }

        $average['sum_orders'] = number_format($average['sum_orders'], 2, '.', ' ');
        $average['tot_customers'] = number_format($average['tot_customers'], 1, '.', ' ');
        $average['tot_orders'] = number_format($average['tot_orders'], 1, '.', ' ');

        return array('orders' => $orders, 'customers' => $customers, 'average' => $average);
    }


    public function get_status_stats() {
        global $wpdb;

        $order_statuses = array();

        if(function_exists('wc_get_order_status_name')) {
            $query = "SELECT COUNT(DISTINCT(posts.ID)) AS count, SUM(meta_order_total.meta_value) AS total, posts.post_status AS code
                        FROM `{$wpdb->posts}` AS posts
                          LEFT JOIN `{$wpdb->postmeta}` AS meta_order_total ON meta_order_total.post_id = posts.ID AND meta_order_total.meta_key = '_order_total'";
        } else {
            $query = "SELECT COUNT(DISTINCT(posts.ID)) AS count, SUM(meta_order_total.meta_value) AS total, status_terms.slug AS code
                        FROM `{$wpdb->posts}` AS posts
                        LEFT JOIN `{$wpdb->postmeta}` AS meta_order_total ON meta_order_total.post_id = posts.ID AND meta_order_total.meta_key = '_order_total'
                        LEFT JOIN `{$wpdb->term_relationships}` AS order_status_terms ON order_status_terms.object_id = posts.ID
                                  AND order_status_terms.term_taxonomy_id IN (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE taxonomy = 'shop_order_status')
                        LEFT JOIN `{$wpdb->terms}` AS status_terms ON status_terms.term_id = order_status_terms.term_taxonomy_id";
        }

        $today = date("Y-m-d", time());
        $date_from = $date_to = $today;

        if(!empty($this->stats_from)) {
            $date_from = $this->stats_from;
        }

        if(!empty($this->stats_to)) {
            $date_to = $this->stats_to;
        }

        if(!empty($this->custom_period) && strlen($this->custom_period) > 0) {
            $custom_period = $this->get_custom_period($this->custom_period);

            $date_from = $custom_period['start_date'];
            $date_to = $custom_period['end_date'];
        }


        $query_where_parts[] = " posts.post_type = 'shop_order' ";
        if(!empty($date_from)) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(posts.post_date_gmt) >= '%d'", strtotime($date_from . " 00:00:00"));
        }

        if(!empty($date_to)) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(posts.post_date_gmt) <= '%d'", strtotime($date_to . " 23:59:59"));
        }

        if(!empty($this->status_list_hide)) {
            $query_where_parts[] = " posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
        }

        if(!empty($query_where_parts)) {
            $query .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        if(function_exists('wc_get_order_status_name')) {
            $query .= " GROUP BY posts.post_status ORDER BY total";
        } else {
            $query .= " GROUP BY order_status_terms.term_taxonomy_id ORDER BY total";
        }

        if($results = $wpdb->get_results($query, ARRAY_A)) {
            foreach ($results as $row) {
                if($row['count'] == 0) {
                    continue;
                }

                $row['total'] = nice_price($row['total'], $this->currency);
                $row['name'] = _get_order_status_name(0, $row['code']);

                $order_statuses[] = $row;
            }
        }

        return $order_statuses;
    }


    private function _get_total_customers($data) {
        global $wpdb;
        $query_where_parts = array();

        $query = "SELECT COUNT(DISTINCT(c.ID)) AS count_customers
                  FROM `{$wpdb->users}` AS c
                      LEFT JOIN `{$wpdb->usermeta}` AS usermeta ON usermeta.user_id = c.ID";

        $query_where_parts[] = " usermeta.meta_key = 'wp_capabilities' AND usermeta.meta_value LIKE '%customer%' ";

        if(!empty($data['date_from'])) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(c.user_registered) >= '%d'", strtotime($data['date_from']));
        }

        if(!empty($data['date_to'])) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(c.user_registered) <= '%d'", strtotime($data['date_to']));
        }


        if(!empty($query_where_parts)) {
            $query .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        $totals = $wpdb->get_row($query, ARRAY_A);

        $totals['count_customers'] = nice_count($totals['count_customers']);

        return $totals;
    }

    private function _get_total_orders_i_products($data) {
        global $wpdb;
        $query_where_parts = array();

        $query_orders = "SELECT
              COUNT(posts.ID) AS count_orders,
              SUM(meta_order_total.meta_value) AS total_sales
            FROM `{$wpdb->posts}` AS posts
            LEFT JOIN `{$wpdb->postmeta}` AS meta_order_total ON meta_order_total.post_id = posts.ID AND meta_order_total.meta_key = '_order_total'";

        $query_products = "SELECT
              SUM(meta_items_qty.meta_value) AS count_products
            FROM `{$wpdb->posts}` AS posts
            LEFT JOIN `{$wpdb->prefix}woocommerce_order_items` AS order_items ON order_items.order_id = posts.ID AND order_items.order_item_type = 'line_item'
            LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` AS meta_items_qty ON meta_items_qty.order_item_id = order_items.order_item_id AND meta_items_qty.meta_key = '_qty'";

        if(!function_exists('wc_get_order_status_name')) {
            $query = " LEFT JOIN `{$wpdb->term_relationships}` AS order_status_terms ON order_status_terms.object_id = posts.ID
                            AND order_status_terms.term_taxonomy_id IN (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE taxonomy = 'shop_order_status')
                        LEFT JOIN `{$wpdb->terms}` AS status_terms ON status_terms.term_id = order_status_terms.term_taxonomy_id";
            $query_orders .= $query;
            $query_products .= $query;
        }

		$query_where_parts[] = " posts.post_type = 'shop_order' ";
	
        if (isset($data['date_from'])) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(posts.post_date_gmt) >= '%d'", strtotime($data['date_from']));
        }

        if (isset($data['date_to'])) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(posts.post_date_gmt) <= '%d'", strtotime($data['date_to']));
        }

        if (isset($data['statuses'])) {
            if(function_exists('wc_get_order_status_name')) {
                $query_where_parts[] = sprintf(" posts.post_status IN ('%s')", $this->get_filter_statuses($data['statuses']));
            } else {
                $query_where_parts[] = sprintf(" status_terms.slug IN ('%s')", $this->get_filter_statuses($data['statuses']));
            }
        }
        
        if(!empty($this->status_list_hide)) {
            $query_where_parts[] = " posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
        }

        if(!empty($query_where_parts)) {
            $query_orders .= " WHERE " . implode(" AND ", $query_where_parts);
            $query_products .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        $orders_stat = $wpdb->get_results($query_orders, ARRAY_A);
        $orders_stat = array_shift($orders_stat);

        $products_stat = $wpdb->get_results($query_products, ARRAY_A);
        $products_stat = array_shift($products_stat);

        $totals['count_orders'] = nice_count($orders_stat['count_orders']);
        $totals['total_sales'] = nice_price($orders_stat['total_sales'], $this->currency);
        $totals['count_products'] = nice_count($products_stat['count_products']);

        return $totals;
    }

    public function get_orders() {
        global $wpdb;


        $sql_total_products = "SELECT SUM(meta_items_qty.meta_value)
            FROM `{$wpdb->prefix}woocommerce_order_items` AS order_items
            LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` AS meta_items_qty ON meta_items_qty.order_item_id = order_items.order_item_id AND meta_items_qty.meta_key = '_qty'
            WHERE order_items.order_item_type = 'line_item' AND order_items.order_id = posts.ID";

        if(function_exists('wc_get_order_status_name')) {
            $status_code_field = "posts.post_status";
        } else {
            $status_code_field = "status_terms.slug";
        }

        $fields = "SELECT
                    posts.ID AS id_order,
                    posts.post_date_gmt AS date_add,
                    meta_order_total.meta_value AS total_paid,
                    meta_order_currency.meta_value AS currency_code,
                    $status_code_field AS status_code,
                    first_name.meta_value AS first_name,
                    last_name.meta_value AS last_name,
                    users.display_name,
                    customer_id.meta_value AS customer_id,
                    ( $sql_total_products ) AS count_prods";

        $total_fields = "SELECT COUNT(DISTINCT(posts.ID)) AS total_orders, SUM(meta_order_total.meta_value) AS total_sales";

        $sql = " FROM `{$wpdb->posts}` AS posts
            LEFT JOIN `{$wpdb->postmeta}` AS meta_order_total ON meta_order_total.post_id = posts.ID AND meta_order_total.meta_key = '_order_total'
            LEFT JOIN `{$wpdb->postmeta}` AS meta_order_currency ON meta_order_currency.post_id = posts.ID AND meta_order_currency.meta_key = '_order_currency'
            LEFT JOIN `{$wpdb->postmeta}` AS customer_id ON customer_id.post_id = posts.ID AND customer_id.meta_key = '_customer_user'
            LEFT JOIN `{$wpdb->usermeta}` AS first_name ON first_name.user_id = customer_id.meta_value AND first_name.meta_key = 'first_name'
            LEFT JOIN `{$wpdb->usermeta}` AS last_name ON last_name.user_id = customer_id.meta_value AND last_name.meta_key = 'last_name'
            LEFT JOIN `{$wpdb->users}` AS users ON users.ID = customer_id.meta_value
        ";

        if(!function_exists('wc_get_order_status_name')) {
            $sql .= " LEFT JOIN `{$wpdb->term_relationships}` AS order_status_terms ON order_status_terms.object_id = posts.ID
                    AND order_status_terms.term_taxonomy_id IN (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE taxonomy = 'shop_order_status')
                LEFT JOIN `{$wpdb->terms}` AS status_terms ON status_terms.term_id = order_status_terms.term_taxonomy_id";
        }

        $query = $fields . $sql;

        $query_totals = $total_fields . $sql;


        $query_where_parts[] = " posts.post_type = 'shop_order' ";

        if(!empty($this->status_list_hide)) {
            $query_where_parts[] = " posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
        }

        if(!empty($this->orders_from)) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(posts.post_date_gmt) >= '%d'", strtotime($this->orders_from." 00:00:00"));
        }

        if(!empty($this->orders_to)) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(posts.post_date_gmt) <= '%d'", strtotime($this->orders_to." 23:59:59"));
        }

        if(!empty($this->search_order_id) && preg_match('/^\d+(?:,\d+)*$/', $this->search_order_id)) {
            $query_where_parts[] = sprintf("posts.ID IN (%s)", $this->search_order_id);

        } elseif (!empty($this->search_order_id)) {
            $query_where_parts[] = sprintf(" (CONCAT(first_name.meta_value, ' ', last_name.meta_value) LIKE '%%%s%%' OR users.display_name LIKE '%%%s%%') ", $this->search_order_id, $this->search_order_id);
        }

        if(!empty($this->statuses)) {
            if(function_exists('wc_get_order_status_name')) {
                $query_where_parts[] = sprintf(" posts.post_status IN ('%s')", $this->get_filter_statuses($this->statuses));
            } else {
                $query_where_parts[] = sprintf(" status_terms.slug IN ('%s')", $this->get_filter_statuses($this->statuses));
            }
        }


        if (!empty($query_where_parts)) {
            $query .= " WHERE " . implode(" AND ", $query_where_parts);
            $query_totals .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        if(empty($this->sort_by)) {
            $this->sort_by = "id";
        }

        $query .= " ORDER BY ";
        switch ($this->sort_by) {
            case 'id':
                $query .= "posts.ID DESC";
                break;
            case 'date':
                $query .= "posts.post_date_gmt DESC";
                break;
            case 'name':
                $query .= "customer ASC";
                break;
        }

        $query .= sprintf(" LIMIT %d, %d", (($this->page - 1)*$this->show), $this->show);

        $totals = $wpdb->get_row($query_totals, ARRAY_A);

        $orders = array();
        $results = $wpdb->get_results($query, ARRAY_A);
        foreach ( $results as $order ) {
            $order['ord_status'] = _get_order_status_name($order['id_order'], $order['status_code']);

//            if (!empty($this->currency)) {
//                $currency_code = $this->currency;
//            } else {
                $currency_code = $order['currency_code'];
//            }



            $order['total_paid'] = nice_price($order['total_paid'], $currency_code);

            $customer_name = trim($order['first_name'] . ' ' . $order['last_name']);
            if($customer_name == null || empty($customer_name)) {
                $customer_name = trim($order['display_name']);
            }
            $order['customer'] = $customer_name;

            if ($order['customer_id'] == 0) {
                $order['customer'] = __('Guest', 'woocommerce');
            }

            $orders[] = $order;
        }

        $orders_status = null;
        if(!empty($this->get_statuses) && $this->get_statuses == 1) {
            $orders_status = $this->get_orders_statuses();
        }

        return array("orders" => $orders,
            "orders_count"    => $totals['total_orders'],
            "orders_total"    => nice_price($totals['total_sales'], $this->currency),
            "orders_status"   => $orders_status
        );
    }


    public function get_orders_info() {
        global $woocommerce;
        $this->order_id = _validate_post( $this->order_id, 'shop_order' );

        if(!$this->order_id || empty($this->order_id)) {
            return false;
        }

        $order = new WC_Order($this->order_id);
        //$user = $order->get_user();
        $user = new WP_User( $order->user_id );

//        if (!empty($this->currency)) {
//            $currency_code = $this->currency;
//        } else {
        if(method_exists($order, 'get_order_currency')) {
            $currency_code = $order->get_order_currency();
        } else {
            $currency_code = $this->currency;
        }
//        }

        $customer_name = trim($user->first_name . ' ' . $user->last_name);
        if($customer_name == null || empty($customer_name)) {
           // $customer_name = trim($user->data->display_name);
            $customer_name = trim($order->billing_first_name . ' ' . $order->billing_last_name);
        }
        if($customer_name == null || empty($customer_name)) {
            $customer_name = __('Guest', 'woocommerce');
        }

        $order_total = nice_price($order->get_total(), $currency_code);
        $countries = $woocommerce->countries->countries;

        $order_info = array(
            'id_order'          => $order->id,
            'id_customer'       => $order->user_id,
            'email'             => isset($user->data->user_email) ? $user->data->user_email : $order->billing_email,
            'customer'          => $customer_name,
            'date_added'        => $order->order_date,
            'status_code'       => $order->status,
            'status'            => _get_order_status_name($order->id, $order->status),
            'total'             => $order_total,
            'currency_code'     => $currency_code,

            'p_method'          => $order->payment_method_title,
            'b_name'            => $order->billing_first_name . ' ' . $order->billing_last_name,
            'b_company'         => $order->billing_company,
            'b_address_1'       => $order->billing_address_1,
            'b_address_2'       => $order->billing_address_2,
            'b_city'            => $order->billing_city,
            'b_postcode'        => $order->billing_postcode,
            'b_country'         => $countries[$order->billing_country],
            'b_state'           => $order->billing_state,
            'b_email'           => $order->billing_email,
            'b_telephone'       => $order->billing_phone,

            's_method'          => $order->get_shipping_method(),
            's_name'            => $order->shipping_first_name . ' ' . $order->shipping_last_name,
            's_company'         => $order->shipping_company,
            's_address_1'       => $order->shipping_address_1,
            's_address_2'       => $order->shipping_address_2,
            's_city'            => $order->shipping_city,
            's_postcode'        => $order->shipping_postcode,
            's_country'         => $countries[$order->shipping_country],
            's_state'           => $order->shipping_state,

            'total_shipping'    => nice_price($order->order_shipping, $currency_code),
            'discount'          => nice_price($order->order_discount, $currency_code),
            'tax_amount'        => nice_price($order->order_tax, $currency_code),
            'order_total'            => $order_total,

            'admin_comments'    => $this->_get_order_notes($order->id),
        );

        if(method_exists($order, 'get_total_refunded')) {
            $order_info['t_refunded'] = nice_price($order->get_total_refunded()*-1, $currency_code);
        }

        $products = $this->_get_order_products();

        $order_products = array();

        foreach($products as $product) {
            $product['product_price'] = nice_price($product['product_price'], $currency_code);
            $product['product_type'] = $this->_get_product_type($product['product_id']);

            if (strtolower($product['product_type']) == 'variable' && $product['variation_id'] > 0) {
                $var = new WC_Product_Variation( $product['variation_id'] );
                if($var) {
                    $attributes = $var->get_variation_attributes();
                    $variation_data = array();
                    if (is_array($attributes) && count($attributes) > 0) {
                        foreach ($attributes as $name => $attribute) {
                            $attribute_label = str_replace('attribute_', '', $name);
                            if(function_exists('wc_attribute_label')) {
                                $attribute_label = wc_attribute_label($attribute_label);
                            }

                            $variation_data[] = array('attribute' => $attribute_label . ': <strong>' . $attribute . '</strong>');
                        }
                    }

                    $product['product_variation'] = $variation_data;
                }
            }

            $order_products[] = $product;
        }

        $order_full_info = array("order_info" => $order_info, "order_products" => $order_products, "o_products_count" => $order->get_item_count());
        return $order_full_info;
    }


    private function _get_order_products() {
        global $wpdb;

        $query = "SELECT
                    meta_product_id.meta_value AS product_id,
                    posts.post_title AS product_name,
                    items_qty.meta_value AS product_quantity,
                    meta_price.meta_value AS product_price,
                    items_variation_id.meta_value AS variation_id,
                    meta_sku.meta_value AS sku
                  FROM `{$wpdb->prefix}woocommerce_order_items` AS order_items
                    LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` AS meta_product_id ON meta_product_id.order_item_id = order_items.order_item_id AND meta_product_id.meta_key = '_product_id'
                    LEFT JOIN `{$wpdb->posts}` AS posts ON posts.ID = meta_product_id.meta_value
                    LEFT JOIN `$wpdb->postmeta` AS meta_price ON posts.ID = meta_price.post_id AND meta_price.meta_key = '_price'
                    LEFT JOIN `$wpdb->postmeta` AS meta_sku ON posts.ID = meta_sku.post_id AND meta_sku.meta_key = '_sku'
                    LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` AS items_qty ON items_qty.order_item_id = order_items.order_item_id AND items_qty.meta_key = '_qty'
                    LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` AS items_variation_id ON items_variation_id.order_item_id = order_items.order_item_id AND items_variation_id.meta_key = '_variation_id'
                    LEFT JOIN `{$wpdb->posts}` AS posts_orders ON posts_orders.ID = order_items.order_id
                WHERE order_items.order_item_type = 'line_item'
                AND posts.post_type = 'product'
                AND order_items.order_id = '%d'";

        if(!empty($this->status_list_hide)) {
            $query .= " AND posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
        }

        if(!empty($status_list_hide)) {
            $query_where_parts[] = " posts.post_status NOT IN ( '" . implode( $status_list_hide, "', '") . "' )";
        }

        $query = sprintf($query, $this->order_id);

        $results = $wpdb->get_results($query, ARRAY_A);

        return $results;
    }


    public function get_orders_statuses() {
        $orders_statuses = array();

        $statuses = _get_order_statuses();

        foreach($statuses as $code => $name) {
            $orders_statuses[] = array('st_id' => $code, 'st_name' => $name);
        }

        return $orders_statuses;
    }


    public function get_customers() {
        global $wpdb;
        $query_where_parts = array();

        $fields = "SELECT
                DISTINCT(c.ID) AS id_customer,
                um_first_name.meta_value AS firstname,
                um_last_name.meta_value AS lastname,
                CONCAT(um_first_name.meta_value, ' ', um_last_name.meta_value) AS full_name,
                c.user_registered AS date_add,
                c.user_email AS email,
                c.display_name,
                tot.total_orders";

        $total_fields = "SELECT COUNT(DISTINCT(c.ID)) AS count_custs";

        $sql = " FROM `{$wpdb->users}` AS c
                  LEFT JOIN `{$wpdb->usermeta}` AS um_first_name ON um_first_name.user_id = c.ID AND um_first_name.meta_key = 'first_name'
				  LEFT JOIN `{$wpdb->usermeta}` AS um_last_name ON um_last_name.user_id = c.ID AND um_last_name.meta_key = 'last_name'
				  LEFT JOIN `{$wpdb->usermeta}` AS cap ON cap.user_id = c.ID
				  LEFT OUTER JOIN (
                    SELECT COUNT(DISTINCT(posts.ID)) AS total_orders, meta.meta_value AS id_customer FROM `{$wpdb->posts}` AS posts
                    LEFT JOIN `{$wpdb->postmeta}` AS meta ON posts.ID = meta.post_id
                    WHERE meta.meta_key = '_customer_user'
                    AND posts.post_type = 'shop_order'";

        if(!empty($this->status_list_hide)) {
            $sql .= " AND posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
        }

        $sql .= " GROUP BY meta.meta_value ) AS tot ON tot.id_customer = c.ID";


        $query = $fields . $sql;
        $query_page = $total_fields . $sql;

        $query_where_parts[] = " cap.meta_key = '{$wpdb->prefix}capabilities' AND cap.meta_value LIKE '%customer%' ";
        if(!empty($this->customers_from)) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(c.user_registered) >= '%d'", strtotime($this->customers_from." 00:00:00"));
        }
        if(!empty($this->customers_to)) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(c.user_registered) <= '%d'", strtotime($this->customers_to." 23:59:59"));
        }

        if(!empty($this->search_val) && preg_match('/^\d+(?:,\d+)*$/', $this->search_val)) {
            $query_where_parts[] = sprintf("c.ID IN (%s)", $this->search_val);
        } elseif(!empty($this->search_val)) {
            $query_where_parts[] = sprintf(" (c.user_email LIKE '%%%s%%'
                    OR CONCAT(um_first_name.meta_value, ' ', um_last_name.meta_value) LIKE '%%%s%%'
                    OR c.display_name LIKE '%%%s%%')"
                , $this->search_val, $this->search_val, $this->search_val);
        }

        if(!empty($this->cust_with_orders)) {
            $query_where_parts[] = " tot.total_orders > 0";
        }

        if (!empty($query_where_parts)) {
            $query .= " WHERE " . implode(" AND ", $query_where_parts);
            $query_page .= " WHERE " . implode(" AND ", $query_where_parts);
        }

        if(empty($this->sort_by)) {
            $this->sort_by = "id";
        }

        $query .= " ORDER BY ";
        switch ($this->sort_by) {
            case 'id':
                $query .= "c.ID DESC";
                break;
            case 'date':
                $query .= "c.user_registered DESC";
                break;
            case 'name':
                $query .= "full_name ASC";
                break;
        }

        $query .= sprintf(" LIMIT %d, %d", (($this->page - 1)*$this->show), $this->show);

        $customers = array();
        $results = $wpdb->get_results($query, ARRAY_A);
        foreach ( $results as $user ) {
            $date = explode(' ', $user['date_add']);
            $user['date_add'] = $date[0];
            $user['total_orders'] = intval($user['total_orders']);

            if($user['full_name'] == null || trim($user['full_name']) == '') {
                $user['full_name'] = $user['display_name'];
                $user['firstname'] = $user['display_name'];
            }

            $customers[] = $user;
        }

        $row_page = $wpdb->get_row($query_page, ARRAY_A);

        return array(
            "customers_count" => intval($row_page['count_custs']),
            "customers" => $customers
        );
    }


    public function get_customers_info() {
        //global $wp_roles;

        $this->user_id = _validate_post( $this->user_id, 'customer' );

        if(!$this->user_id || empty($this->user_id)) {
            return false;
        }

        $user = new WP_User( $this->user_id );

        if(!$user) {
            return false;
        }

        $customer_name = trim($user->first_name . ' ' . $user->last_name);
        if($customer_name == null || empty($customer_name)) {
            $customer_name = trim($user->data->display_name);
        }

/*
        $all_roles = $wp_roles->roles;
        $editable_roles = apply_filters( 'editable_roles', $all_roles );

        $user_roles = array_intersect( array_values( $user->roles ), array_keys( $editable_roles ) );
        $user_role  = array_shift( $user_roles );

        $editable_roles = array_reverse( $editable_roles );

        if($user_role) {
            $role = $editable_roles[$user_role];
            $role_name = $role['name'];
        } else {
            $role_name = __('No role for this site');
        }
*/
        $customer_general_info = array(
            'username'      => $user->data->user_login,
//            'role'          => $role_name,
//            'first_name'    => $user->first_name,
//            'last_name'     => $user->last_name,
            'nickname'        => $user->nickname,
            'display_name'  => $user->data->display_name,
            'email'         => $user->data->user_email,
            'website'       => $user->data->user_url,
            'date_add'      => $user->user_registered,
        );
        $customer_billing_info = array(
            'b_firstname'   => $user->billing_first_name,
            'b_lastname'    => $user->billing_last_name,
            'b_company'     => $user->billing_company,
            'b_address_1'    => $user->billing_address_1,
            'b_address_2'    => $user->billing_address_2,
            'b_city'        => $user->billing_city,
            'b_postcode'    => $user->billing_postcode,
            'b_state'       => $user->billing_state,
            'b_country'     => $user->billing_country,
            'b_phone'       => $user->billing_phone,
            'b_email'       => $user->billing_email,
        );
        $customer_shipping_info = array(
            's_firstname'   => $user->shipping_first_name,
            's_lastname'    => $user->shipping_last_name,
            's_company'     => $user->shipping_company,
            's_address_1'    => $user->shipping_address_1,
            's_address_2'    => $user->shipping_address_2,
            's_city'        => $user->shipping_city,
            's_postcode'    => $user->shipping_postcode,
            's_state'       => $user->shipping_state,
            's_country'     => $user->shipping_country,
        );

        $customer = array(
            'customer_id' => $user->ID,
            'name' => $customer_name,
            'general_info'  => $customer_general_info,
            'billing_info'  => $customer_billing_info,
            'shipping_info' => $customer_shipping_info,
        );

        $customer_orders = $this->_get_customer_orders($user->ID);
        $customer_order_totals = $this->_get_customer_orders_total($user->ID);

        $customer_info = array("user_info" => $customer, "customer_orders" => $customer_orders);
        $customer_info = array_merge($customer_info, $customer_order_totals);

        return $customer_info;
    }


    private function _get_customer_orders($id) {
        global $wpdb;

        $customer = new WP_User( $id );

        if ( $customer->ID == 0 ) {
            return false;
        }

        $sql = "SELECT
                    posts.ID AS id_order,
                    meta_total.meta_value AS total_paid,
                    meta_curr.meta_value AS currency_code,
                    posts.post_status AS order_status_id,
                    posts.post_date_gmt as date_add,
                    (SELECT SUM(meta_value) FROM `{$wpdb->prefix}woocommerce_order_itemmeta` WHERE order_item_id = order_items.order_item_id AND meta_key = '_qty') AS pr_qty
                FROM `$wpdb->posts` AS posts
                    LEFT JOIN `{$wpdb->postmeta}` AS meta ON posts.ID = meta.post_id
                    LEFT JOIN `{$wpdb->postmeta}` AS meta_total ON meta_total.post_id = posts.ID AND meta_total.meta_key = '_order_total'
                    LEFT JOIN `{$wpdb->postmeta}` AS meta_curr ON meta_curr.post_id = posts.ID AND meta_curr.meta_key = '_order_currency'
                    LEFT JOIN `{$wpdb->prefix}woocommerce_order_items` AS order_items on order_items.order_id = posts.ID AND order_item_type = 'line_item'
                WHERE meta.meta_key = '_customer_user'
                    AND meta.meta_value = '%s'
                    AND posts.post_type = 'shop_order'";

        if(!empty($this->status_list_hide)) {
            $sql .= " AND posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
        }

        $sql .= " GROUP BY order_items.order_id";

        $sql .= sprintf(" LIMIT %d, %d", (($this->page - 1)*$this->show), $this->show);

        $query = $wpdb->prepare( $sql, $id );

        $orders = array();
        $results = $wpdb->get_results($query, ARRAY_A);
        foreach ( $results as $order ) {
            $order['total_paid'] = nice_price($order['total_paid'], $order['currency_code']);
            $order['ord_status'] = _get_order_status_name($order['id_order'], $order['order_status_id']);
            $orders[] = $order;
        }

        return $orders;
    }

    private function _get_customer_orders_total($id) {
        global $wpdb;

        $customer = new WP_User( $id );

        if ( $customer->ID == 0 ) {
            return false;
        }

        $sql = "SELECT COUNT(DISTINCT(posts.ID)) AS c_orders_count, SUM(meta_total.meta_value) AS sum_ords
                FROM `$wpdb->posts` AS posts
                    LEFT JOIN `{$wpdb->postmeta}` AS meta ON posts.ID = meta.post_id
                    LEFT JOIN `{$wpdb->postmeta}` AS meta_total ON meta_total.post_id = posts.ID AND meta_total.meta_key = '_order_total'
                    LEFT JOIN `{$wpdb->postmeta}` AS meta_curr ON meta_curr.post_id = posts.ID AND meta_curr.meta_key = '_order_currency'
                    LEFT JOIN `{$wpdb->prefix}woocommerce_order_items` AS order_items on order_items.order_id = posts.ID AND order_item_type = 'line_item'
                WHERE meta.meta_key = '_customer_user'
                    AND meta.meta_value = '%s'
                    AND posts.post_type = 'shop_order'";

        if(!empty($this->status_list_hide)) {
            $sql .= " AND posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
        }

        $sql = $wpdb->prepare( $sql, $id );

        $orders_total = array("c_orders_count" => 0, "sum_ords" => 0);
        if($row_total = $wpdb->get_row($sql, ARRAY_A)) {
            $orders_total = $row_total;
        }

        $orders_total['sum_ords'] = nice_price($orders_total['sum_ords'], $this->currency);
        $orders_total['c_orders_count'] = $orders_total['c_orders_count'];

        return $orders_total;
    }


    public function search_products() {
        global $wpdb;

        $fields = "SELECT
            posts.ID AS product_id,
            posts.post_title AS name,
            meta_price.meta_value AS price,
            meta_sku.meta_value AS sku,
            meta_stock.meta_value AS quantity";

        $fields_total = "SELECT COUNT(DISTINCT(posts.ID)) AS count_prods";

        $sql = " FROM `$wpdb->posts` AS posts
            LEFT JOIN `$wpdb->postmeta` AS meta_price ON meta_price.post_id = posts.ID AND meta_price.meta_key = '_price'
            LEFT JOIN `$wpdb->postmeta` AS meta_sku ON meta_sku.post_id = posts.ID AND meta_sku.meta_key = '_sku'
            LEFT JOIN `$wpdb->postmeta` AS meta_stock ON meta_stock.post_id = posts.ID AND meta_stock.meta_key = '_stock'
		WHERE posts.post_type = 'product'";

        if(!empty($this->status_list_hide)) {
            $sql .= " AND posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
        }

        $products = $this->_get_products($fields, $fields_total, $sql);

        return $products;
    }


    public function search_products_ordered() {
        global $wpdb;

        $fields = "SELECT
            posts.ID AS product_id,
            posts_orders.ID AS order_id,
            posts.post_title AS name,
            meta_price.meta_value AS price,
            meta_sku.meta_value AS sku,
            meta_stock.meta_value AS quantity";

        $fields_total = "SELECT COUNT(DISTINCT(posts.ID)) AS count_prods";

        $sql = " FROM `{$wpdb->prefix}woocommerce_order_items` AS order_items
                    LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` AS meta_product_id ON meta_product_id.order_item_id = order_items.order_item_id AND meta_product_id.meta_key = '_product_id'
                    LEFT JOIN `{$wpdb->posts}` AS posts ON posts.ID = meta_product_id.meta_value
                    LEFT JOIN `{$wpdb->postmeta}` AS meta_price ON posts.ID = meta_price.post_id AND meta_price.meta_key = '_price'
                    LEFT JOIN `{$wpdb->postmeta}` AS meta_sku ON posts.ID = meta_sku.post_id AND meta_sku.meta_key = '_sku'
                    LEFT JOIN `{$wpdb->postmeta}` AS meta_stock ON posts.ID = meta_stock.post_id AND meta_stock.meta_key = '_stock'
                    LEFT JOIN `{$wpdb->posts}` AS posts_orders ON posts_orders.ID = order_items.order_id";

        if(!function_exists('wc_get_order_status_name')) {
            $sql .= " LEFT JOIN `{$wpdb->term_relationships}` AS order_status_terms ON order_status_terms.object_id = posts_orders.ID
                            AND order_status_terms.term_taxonomy_id IN (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE taxonomy = 'shop_order_status')
                        LEFT JOIN `{$wpdb->terms}` AS status_terms ON status_terms.term_id = order_status_terms.term_taxonomy_id";
        }

        $sql .= " WHERE order_items.order_item_type = 'line_item'
                AND posts.post_type = 'product'";

        if(!empty($this->status_list_hide)) {
            $sql .= " AND posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
        }

        $products = $this->_get_products($fields, $fields_total, $sql);

        return $products;
    }


    private function _get_products($fields, $fields_total, $sql) {
        global $wpdb;
        $query_where_parts = array();

        $query = $fields . $sql;
        $query_total = $fields_total . $sql;

        if(!empty($this->params) && !empty($this->val)) {
            $params = explode("|", $this->params);
			
            foreach($params as $param) {
                switch ($param) {
                    case 'pr_id':
                        $query_params_parts[] = sprintf(" posts.ID LIKE '%%%s%%'", $this->val);
                        break;
                    case 'pr_sku':
                        $query_params_parts[] = sprintf(" meta_sku.meta_value LIKE '%%%s%%'", $this->val);
                        break;
                    case 'pr_name':
                        $query_params_parts[] = sprintf(" posts.post_title LIKE '%%%s%%'", $this->val);
                        break;
                }
            }
        }
		 if(!empty($this->status_list_hide)) {
			$query_where_parts[] = " posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
		}
		
        if(!empty($this->statuses)) {
            if(function_exists('wc_get_order_status_name')) {
                $query_where_parts[] = sprintf(" posts_orders.post_status IN ('%s')", $this->get_filter_statuses($this->statuses));
            } else {
                $query_where_parts[] = sprintf(" status_terms.slug IN ('%s')", $this->get_filter_statuses($this->statuses));
            }
        }

        if (!empty($this->products_from)) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(posts_orders.post_date_gmt) >= '%d'", strtotime($this->products_from . " 00:00:00"));
        }

        if (!empty($this->products_to)) {
            $query_where_parts[] = sprintf(" UNIX_TIMESTAMP(posts_orders.post_date_gmt) <= '%d'", strtotime($this->products_to . " 23:59:59"));
        }

        if (!empty($query_params_parts)) {
            $query_where_parts[] = " ( " . implode(" OR ", $query_params_parts) . " )";
        }


        if (!empty($query_where_parts)) {
            $query .= " AND " . implode(" AND ", $query_where_parts);
            $query_total .= " AND " . implode(" AND ", $query_where_parts);
        }

        if(empty($this->sort_by)) {
            $this->sort_by = "id";
        }

        $query .= " GROUP BY posts.ID ORDER BY ";
        switch ($this->sort_by) {
            case 'id':
                $query .= "posts.ID DESC";
                break;
            case 'name':
                $query .= "posts.post_title ASC";
                break;
        }

        $query .= sprintf(" LIMIT %d, %d", (($this->page - 1)*$this->show), $this->show);

        $products_count = array("count_prods" => 0,);
        if($row_total = $wpdb->get_row($query_total, ARRAY_A)) {
            $products_count = $row_total;
        }

        $products = array();
        $results = $wpdb->get_results( $query, ARRAY_A );
        foreach($results as $product) {
            $product['sale_price'] = isset($product['sale_price']) ? nice_price($product['sale_price'], $this->currency) : NULL ;
            $product['price'] = nice_price($product['price'], $this->currency);
            $product['quantity'] = intval($product['quantity']);
            $product['product_type'] = $this->_get_product_type($product['product_id']);

            $products[] = $product;
        }

        return array("products_count" => nice_count($products_count['count_prods']), "products" => $products);;
    }


    public function get_products_info() {
        global $wpdb;

        $this->product_id = _validate_post( $this->product_id, 'product' );

        if(!$this->product_id || empty($this->product_id)) {
            return false;
        }

        $sql_total_ordered = "SELECT SUM(meta_items_qty.meta_value)
            FROM `{$wpdb->prefix}woocommerce_order_itemmeta` AS order_itemmeta
              LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` AS meta_items_qty ON order_itemmeta.order_item_id = meta_items_qty.order_item_id AND meta_items_qty.meta_key = '_qty'
            WHERE order_itemmeta.meta_key LIKE '_product_id' AND order_itemmeta.meta_value = posts.ID";

        $sql = "SELECT
                posts.ID AS product_id,
                posts.post_title AS name,
                meta_price.meta_value AS price,
                meta_sku.meta_value AS sku,
                meta_stock.meta_value AS quantity,
                ({$sql_total_ordered}) AS total_ordered,
                posts.post_status
            FROM `$wpdb->posts` AS posts
                LEFT JOIN `$wpdb->postmeta` AS meta_price ON meta_price.post_id = posts.ID AND meta_price.meta_key = '_price'
                LEFT JOIN `$wpdb->postmeta` AS meta_sku ON meta_sku.post_id = posts.ID AND meta_sku.meta_key = '_sku'
                LEFT JOIN `$wpdb->postmeta` AS meta_stock ON meta_stock.post_id = posts.ID AND meta_stock.meta_key = '_stock'
            WHERE posts.post_type = 'product'
                AND posts.ID = '%d'";

        if(!empty($this->status_list_hide)) {
            $sql .= " AND posts.post_status NOT IN ( '" . implode( $this->status_list_hide, "', '") . "' )";
        }

        $sql = sprintf($sql, $this->product_id);

        $product = $wpdb->get_row($sql, ARRAY_A);

        $product['sale_price'] = isset($product['sale_price']) ? nice_price($product['sale_price'], $this->currency) : NULL;
        $product['price'] = nice_price($product['price'], $this->currency);
        $product['quantity'] = intval($product['quantity']);
        $product['total_ordered'] = intval($product['total_ordered']);
		
		$stat = 'Undefined';
		
        switch ( $product['post_status'] ) {
            case 'publish' :
            case 'private' :
                $stat = __('Published');
                break;
            case 'future' :
                $stat = __('Scheduled');
                break;
            case 'pending' :
                $stat = __('Pending Review');
                break;
            case 'draft' :
                $stat = __('Draft');
				break;
			case 'private' :
                $stat = __('private');
				break;
			case 'trash' :
                $stat = __('Trash');
                break;
        }
        $product['forsale'] = $stat;

        $product['product_type'] = $this->_get_product_type($this->product_id);

        $attachment_id = get_post_thumbnail_id( $product['product_id'] );

        $id_image_large = wp_get_attachment_image_src( $attachment_id, 'large' );
        $product['id_image_large'] = $id_image_large[0];

        $id_image = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
        $product['id_image'] = $id_image[0];

        return $product;
    }

    private function _get_product_type($product_id) {
        if(function_exists('wc_get_product')) {
            $the_product = wc_get_product( $product_id );
        } else {
            $the_product = get_product($product_id);
        }

        $type = '';
        if ( 'grouped' == $the_product->product_type ) {
            $type = __( 'Grouped', 'woocommerce' );

        } elseif ( 'external' == $the_product->product_type ) {
            $type = __( 'External/Affiliate', 'woocommerce' );

        } elseif ( 'simple' == $the_product->product_type ) {

            if ( $the_product->is_virtual() ) {
                $type = __( 'Virtual', 'woocommerce' );

            } elseif ( $the_product->is_downloadable() ) {
                $type = __( 'Downloadable', 'woocommerce' );

            } else {
                $type = __( 'Simple', 'woocommerce' );
            }

        } elseif ( 'variable' == $the_product->product_type ) {
            $type = __( 'Variable', 'woocommerce' );

        } else {
            $type = ucfirst( $the_product->product_type );
        }

        return $type;
    }

    public function get_products_descr() {
        global $wpdb;

        $sql = "SELECT post_content AS descr, post_excerpt AS short_descr FROM `$wpdb->posts` WHERE post_type = 'product' AND ID = '%d'";

        $sql = sprintf($sql, $this->product_id);

        if($product_descr = $wpdb->get_row($sql, ARRAY_A)) {
            return $product_descr;
        }

        return false;
    }


    public function set_order_action() {
        if ($this->order_id <= 0) {
            $error = 'Order ID cannot be empty!';
            log_me('ORDER ACTION ERROR: ' . $error);
            return array('error' => $error);
        }

        if(empty($this->action)) {
            $error = 'Action is not set!';
            log_me('ORDER ACTION ERROR: ' . $error);
            return array('error' => $error);
        }

        $order = new WC_Order($this->order_id);

        if(!$order) {
            $error = 'Order not found!';
            log_me('ORDER ACTION ERROR: ' . $error);
            return array('error' => $error);
        }

        if($this->action == 'change_status') {
            if (!isset($this->new_status) || intval($this->new_status) < 0) {
                $error = 'New order status is not set!';
                log_me('ORDER ACTION ERROR: ' . $error);
                return array('error' => $error);
            }

            $order->update_status($this->new_status, $this->change_order_status_comment);

            return array('success' => 'true');
        }

        $error = 'Unknown error!';
        log_me('ORDER ACTION ERROR: ' . $error);
        return array('error' => $error);
    }



//== PUSH ===========================================================================

    public function push_notification_settings() {
        $data = array();

        if(empty($this->registration_id)) {
            $error = 'Empty device ID';
            log_me('PUSH SETTINGS ERROR: ' . $error);
            return array('error' => $error);
        }

        if(empty($this->app_connection_id) || $this->app_connection_id < 0) {
            $error = 'Wrong app connection ID: ' . $this->app_connection_id;
            log_me('PUSH SETTINGS ERROR: ' . $error);
            return array('error' => $error);
        }

        if(empty($this->api_key)) {
            $error = 'Empty application API key';
            log_me('PUSH SETTINGS ERROR: ' . $error);
            return array('error' => $error);
        }

        $options = get_option('mobassistantconnector');
        if(!isset($options['mobassist_api_key']) || $options['mobassist_api_key'] != $this->api_key) {
            $options['mobassist_api_key'] = $this->api_key;
            update_option('mobassistantconnector', $options);
        }

        $data['registration_id'] = $this->registration_id;
        $data['app_connection_id'] = $this->app_connection_id;
        $data['push_new_order'] = $this->push_new_order;
        $data['push_order_statuses'] = $this->push_order_statuses;
        $data['push_new_customer'] = $this->push_new_customer;
        $data['push_currency_code'] = $this->push_currency_code;

        if(!empty($this->registration_id_old)) {
            $data['registration_id_old'] = $this->registration_id_old;
        }

        if($this->savePushNotificationSettings($data)) {
            return array('success' => 'true');
        }

        $error = 'Unknown occurred!';
        log_me('PUSH SETTINGS ERROR: ' . $error);
        return array('error' => $error);
    }


    public function savePushNotificationSettings($data = array()) {
        global $wpdb;
        $query_values = array();
        $query_where = array();

        if(isset($data['registration_id_old'])) {
            $sql = "UPDATE `{$wpdb->prefix}mobileassistant_push_settings` SET registration_id = '%s' WHERE registration_id = '%s'";
            $sql = sprintf($sql, $data['registration_id'], $data['registration_id_old']);
            $wpdb->query($sql);
        }

        if(empty($data['push_new_order']) && empty($data['push_order_statuses']) && empty($data['push_new_customer'])) {
            $sql_del = "DELETE FROM `{$wpdb->prefix}mobileassistant_push_settings` WHERE registration_id = '%s' AND app_connection_id = '%s'";
            $sql_del = sprintf($sql_del, $data['registration_id'], $data['app_connection_id']);

            $wpdb->query($sql_del);

            return true;
        }

        $query_values[] = sprintf(" push_new_order = '%d'", $data['push_new_order']);
        $query_values[] = sprintf(" push_order_statuses = '%s'", $data['push_order_statuses']);
        $query_values[] = sprintf(" push_new_customer = '%d'", $data['push_new_customer']);
        $query_values[] = sprintf(" push_currency_code = '%s'", $data['push_currency_code']);

        $sql = "SELECT setting_id FROM `{$wpdb->prefix}mobileassistant_push_settings`
                WHERE registration_id = '%s' AND app_connection_id = '%s'";

        $sql = sprintf($sql, $data['registration_id'], $data['app_connection_id']);

        $results = $wpdb->get_results($sql, ARRAY_A);

        if(!$results || count($results) > 1 || count($results) <= 0) {
            if(count($results) > 1) {
                foreach ( $results as $row ) {
                    $sql_del = "DELETE FROM `{$wpdb->prefix}mobileassistant_push_settings` WHERE setting_id = '%d'";
                    $sql_del = sprintf($sql_del, $row['setting_id']);
                    $wpdb->query($sql_del);
                }
            }

            $query_values[] = sprintf(" registration_id = '%s'", $data['registration_id']);
            $query_values[] = sprintf(" app_connection_id = '%s'", $data['app_connection_id']);

            $sql = "INSERT INTO `{$wpdb->prefix}mobileassistant_push_settings` SET ";

            if (!empty($query_values)) {
                $sql .= implode(" , ", $query_values);
            }

            $wpdb->query($sql);
            return true;

        } else {
            $query_where[] = sprintf(" registration_id = '%s'", $data['registration_id']);
            $query_where[] = sprintf(" app_connection_id = '%s'", $data['app_connection_id']);

            $sql = "UPDATE `{$wpdb->prefix}mobileassistant_push_settings` SET ";

            if (!empty($query_values)) {
                $sql .= implode(" , ", $query_values);
            }

            if (!empty($query_where)) {
                $sql .= " WHERE " . implode(" AND ", $query_where);
            }

            $wpdb->query($sql);
            return true;
        }

        return false;
    }



//== PRIVATE ===========================================================================

    private function _parse_datetime($datetime) {
        // Strip millisecond precision (a full stop followed by one or more digits)
        if (strpos($datetime, '.') !== false) {
            $datetime = preg_replace('/\.\d+/', '', $datetime);
        }

        // default timezone to UTC
        $datetime = preg_replace('/[+-]\d+:+\d+$/', '+00:00', $datetime);

        try {
            $datetime = new DateTime($datetime, new DateTimeZone('UTC'));

        } catch (Exception $e) {
            $datetime = new DateTime('@0');
        }

        return $datetime->format('Y-m-d H:i:s');
    }


    private function _get_order_notes( $order_id, $fields = null ) {
        $args = array(
            'post_id' => $order_id,
            'approve' => 'approve',
            'type'    => 'order_note'
        );

        remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );
        remove_filter( 'comments_clauses', 'woocommerce_exclude_order_comments' );

        $notes = get_comments( $args );

        add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );
        add_filter( 'comments_clauses', 'woocommerce_exclude_order_comments' );

        $order_notes = array();

        foreach ( $notes as $note ) {

            $order_notes[] = current( $this->_get_order_note( $order_id, $note->comment_ID, $fields ) );
        }

        $order_notes = apply_filters( 'woocommerce_api_order_notes_response', $order_notes, $order_id, $fields, $notes );

        $notes = array();
        foreach ($order_notes as $note) {
            $temp_note = array('date_added' => $note['created_at'], 'note' => $note['note'],);

            if ($note['customer_note'] == 1) {
                $temp_note['note_type'] = __('Customer note', 'woocommerce');
            } else {
                $temp_note['note_type'] = __('Private note', 'woocommerce');
            }

            $notes[] = $temp_note;
        }


        //return apply_filters( 'woocommerce_api_order_notes_response', $order_notes, $order_id, $fields, $notes, $this->server );
        //return array( 'order_notes' => apply_filters( 'woocommerce_api_order_notes_response', $order_notes, $order_id, $fields, $notes, $this->server ) );
        return $notes;
    }


    private function _get_order_note( $order_id, $id, $fields = null ) {
        $id = absint( $id );

        if ( empty( $id ) ) {
            return new WP_Error( 'woocommerce_api_invalid_order_note_id', __( 'Invalid order note ID', 'woocommerce' ), array( 'status' => 400 ) );
        }

        $note = get_comment( $id );

        if ( is_null( $note ) ) {
            return new WP_Error( 'woocommerce_api_invalid_order_note_id', __( 'An order note with the provided ID could not be found', 'woocommerce' ), array( 'status' => 404 ) );
        }

        $order_note = array(
            'id'            => $note->comment_ID,
            'created_at'    => $this->_parse_datetime( $note->comment_date_gmt ),
            'note'          => $note->comment_content,
            'customer_note' => get_comment_meta( $note->comment_ID, 'is_customer_note', true ) ? true : false,
        );

        return array( 'order_note' => apply_filters( 'woocommerce_api_order_note_response', $order_note, $id, $fields, $note, $order_id, $this ) );
    }
}



function _get_order_status_name($order_id, $post_status) {
    if(function_exists('wc_get_order_status_name')) {
        return wc_get_order_status_name($post_status);
    }

    if($order_id > 0) {
        $terms = wp_get_object_terms($order_id, 'shop_order_status', array('fields' => 'slugs'));
        $status = isset($terms[0]) ? $terms[0] : apply_filters('woocommerce_default_order_status', 'pending');
    } else {
        $status = $post_status;
    }


    $statuses = _get_order_statuses();

    return $statuses[$status];
}


function _get_order_statuses() {
    if(function_exists('wc_get_order_statuses')) {
        return wc_get_order_statuses();
    }

    $statuses = (array) get_terms( 'shop_order_status', array( 'hide_empty' => 0, 'orderby' => 'id' ) );

    $statuses_arr = array();
    foreach($statuses as $status) {
        $statuses_arr[$status->slug] = $status->name;
    }

    return $statuses_arr;
}


//== PUSH ===========================================================================

function mobassist_push_new_order($order_id) {
//    if(!check_module_installed()) {
//        return;
//    }

    $order_id = _validate_post( $order_id, 'shop_order' );
    if(!$order_id || empty($order_id)) {
        return false;
    }

    $order = new WC_Order($order_id);

    $type = PUSH_TYPE_NEW_ORDER;
    sendOrderPushMessage($order, $type);
}


function mobassist_push_change_status($order_id) {
//    if(!check_module_installed()) {
//        return;
//    }

    $order_id = _validate_post( $order_id, 'shop_order' );
    if(!$order_id || empty($order_id)) {
        return false;
    }

    $order = new WC_Order($order_id);

    $type = PUSH_TYPE_CHANGE_ORDER_STATUS;
    sendOrderPushMessage($order, $type);
}


function mobassist_push_new_customer($customer_id) {
//    if(!check_module_installed()) {
//        return;
//    }

    $customer_id = _validate_post( $customer_id, 'customer' );

    if(!$customer_id || empty($customer_id)) {
        return false;
    }

    $customer = new WP_User( $customer_id );

    sendCustomerPushMessage($customer);
}


function sendOrderPushMessage($order, $type) {
    $data = array("type" => $type);

    if($type == PUSH_TYPE_CHANGE_ORDER_STATUS) {
        $data['status'] = $order->post_status;
    }

    $push_devices = getPushDevices($data);

    if(!$push_devices || count($push_devices) <= 0) {
        return;
    }

    $url = get_site_url();
    $url = str_replace("http://", "", $url);
    $url = str_replace("https://", "", $url);

    foreach($push_devices as $push_device) {
//        if(empty($push_device['push_currency_code']) || $push_device['push_currency_code'] == 'not_set') {
//            $currency_code = $order->get_order_currency();
//
//        } else if($push_device['push_currency_code'] == 'base_currency') {
            $currency_code = get_woocommerce_currency();

//        } else {
//            $currency_code = $push_device['push_currency_code'];
//        }

        $message = array(
            "push_notif_type" => $type,
            "order_id" => $order->id,
            "customer_name" => $order->billing_first_name . ' ' . $order->billing_last_name,
            "email" => $order->billing_email,
            "new_status" => _get_order_status_name($order->id, $order->status),
            "total" => nice_price($order->get_total(), $currency_code),
            "store_url" => $url,
            "app_connection_id" => $push_device['app_connection_id']
        );

        sendPush2Google($push_device['setting_id'], $push_device['registration_id'], $message);
    }
}


function sendCustomerPushMessage($customer) {
    $type = PUSH_TYPE_NEW_CUSTOMER;
    $data = array("type" => $type);

    $push_devices = getPushDevices($data);

    if(!$push_devices || count($push_devices) <= 0) {
        return;
    }

    $url = get_site_url();
    $url = str_replace("http://", "", $url);
    $url = str_replace("https://", "", $url);

    $customer_name = trim($customer->first_name . ' ' . $customer->last_name);
    if($customer_name == null || empty($customer_name)) {
        $customer_name = trim($customer->data->display_name);
    }

    foreach($push_devices as $push_device) {
        $message = array(
            "push_notif_type" => $type,
            "customer_id" => $customer->ID,
            "customer_name" => $customer_name,
            "email" => $customer->user_email,
            "store_url" => $url,
            "app_connection_id" => $push_device['app_connection_id']
        );

        sendPush2Google($push_device['setting_id'], $push_device['registration_id'], $message);
    }
}


function sendPush2Google($setting_id, $registration_id, $message) {
    $options = get_option('mobassistantconnector');
    if(!isset($options['mobassist_api_key'])) {
        $apiKey = "AIzaSyBSh9Z-D0xOo0BdVs5EgSq62v10RhEEHMY";
    } else {
        $apiKey = $options['mobassist_api_key'];
    }

    $headers = array('Authorization: key=' . $apiKey, 'Content-Type: application/json');

    $post_data = array(
        'registration_ids' => array($registration_id),
        'data' => array("message" => $message)
    );

    if(!function_exists('json_encode')) {
        $post_data = to_json($post_data);
    } else {
        $post_data = json_encode($post_data);
    }

    log_me('PUSH REQUEST DATA: ' . $post_data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://android.googleapis.com/gcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    $response = curl_exec($ch);

    $info = curl_getinfo($ch);

    onResponse($setting_id, $response, $info);
}


function onResponse($setting_id, $response, $info) {
    $code = $info != null && isset($info['http_code']) ? $info['http_code'] : 0;

    $codeGroup = (int)($code / 100);
    if ($codeGroup == 5) {
        log_me('PUSH RESPONSE: code: '.$code.' :: GCM server not available');
        return;
    }
    if ($code !== 200) {
        log_me('PUSH RESPONSE: code: '.$code);
        return;
    }
    if (!$response || strlen(trim($response)) == null) {
        log_me('PUSH RESPONSE: null response');
        return;
    }

    if ($response) {
        $json = json_decode($response, true);
        if (!$json) {
            log_me('PUSH RESPONSE: json decode error');
        }
    }

    $failure = isset($json['failure']) ? $json['failure'] : null;
    $canonicalIds = isset($json['canonical_ids']) ? $json['canonical_ids'] : null;

    if ($failure || $canonicalIds) {
        $results = isset($json['results']) ? $json['results'] : array();
        foreach($results as $result) {
            $newRegId = isset($result['registration_id']) ? $result['registration_id'] : null;
            $error = isset($result['error']) ? $result['error'] : null;
            if ($newRegId) {
                updatePushRegId($setting_id, $newRegId);

            } else if ($error) {
                if ($error == 'NotRegistered' || $error == 'InvalidRegistration') {
                    deletePushRegId($setting_id);
                }
                log_me('PUSH RESPONSE: error: ' . $error);
            }
        }
    }
}


function updatePushRegId($setting_id, $new_reg_id) {
    global $wpdb;

    $sql = "UPDATE `{$wpdb->prefix}mobileassistant_push_settings` SET registration_id = '%s' WHERE setting_id = '%d'";
    $sql = sprintf($sql, $new_reg_id, $setting_id);
    $wpdb->query($sql);
}


function deletePushRegId($setting_id) {
    global $wpdb;

    $sql = "DELETE FROM `{$wpdb->prefix}mobileassistant_push_settings` WHERE setting_id = '%d'";
    $sql = sprintf($sql, $setting_id);
    $wpdb->query($sql);
}


function getPushDevices($data = array()) {
    global $wpdb;

    $sql = "SELECT setting_id, registration_id, app_connection_id, push_currency_code FROM `{$wpdb->prefix}mobileassistant_push_settings` ";

    switch ($data['type']) {
        case PUSH_TYPE_NEW_ORDER:
            $query_where[] = " push_new_order = '1' ";
            break;

        case PUSH_TYPE_CHANGE_ORDER_STATUS:
            $query_where[] = sprintf(" (push_order_statuses = '%s' OR push_order_statuses LIKE '%%|%s' OR push_order_statuses LIKE '%s|%%' OR push_order_statuses LIKE '%%|%s|%%' OR push_order_statuses = '-1') ", $data['status'], $data['status'], $data['status'], $data['status']);
            break;

        case PUSH_TYPE_NEW_CUSTOMER:
            $query_where[] = " push_new_customer = '1' ";
            break;

        default:
            return false;
    }


    if (!empty($query_where)) {
        $sql .= " WHERE " . implode(" AND ", $query_where);
    }

    $results = $wpdb->get_results($sql, ARRAY_A);

    return $results;
}


function check_module_installed() {
    $this->load->model('mobileassistant/setting');
    $s = $this->model_mobileassistant_setting->getSetting('mobassist');

    if($s && isset($s['mobassist_installed']) && $s['mobassist_installed'] == 1) {
        return true;
    }
    return false;
}


function _validate_post( $id, $type ) {
    $id = absint( $id );

    // validate ID
    if ( empty( $id ) )
        return false;

    // only custom post types have per-post type/permission checks
    if ( 'customer' !== $type ) {

        $post = get_post( $id );

        // for checking permissions, product variations are the same as the product post type
        $post_type = ( 'product_variation' === $post->post_type ) ? 'product' : $post->post_type;

        // validate post type
        if ( $type !== $post_type ) {
            return false;
        }
    }

    if ( 'customer' == $type ) {
        $customer = new WP_User( $id );

        if ( 0 === $customer->ID ) {
            return false;
        }
    }

    return $id;
}


function nice_count($n) {
    return nice_price($n, '', true);
}


function nice_price($n, $currency, $is_count = false) {
    $n = floatval($n);

    if ($n < 0) {
        $n = $n * -1;
        $negative = true;
    } else {
        $negative = false;
    }

    $final_number = trim($n);
    $final_number = str_replace(" ", "", $final_number);
    $suf = "";

    if ($n > 1000000000000000) {
        $final_number = round(($n / 1000000000000000), 2);
        $suf = "P";

    } else if ($n > 1000000000000) {
        $final_number = round(($n / 1000000000000), 2);
        $suf = "T";

    } else if ($n > 1000000000) {
        $final_number = round(($n / 1000000000), 2);
        $suf = "G";

    } else if ($n > 1000000) {
        $final_number = round(($n / 1000000), 2);
        $suf = "M";

    } else if ($n > 10000 && $is_count) {
        $final_number = number_format($n, 0, '', ' ');
    }


    if ($is_count) {
        $final_number = ($negative ? '-' : '') . intval($final_number) . $suf;
    } else {
        $num_decimals = absint(get_option('woocommerce_price_num_decimals'));
        //$currency = isset($args['currency']) ? $args['currency'] : '';
        $currency_symbol = get_woocommerce_currency_symbol($currency);
        $decimal_sep = wp_specialchars_decode(stripslashes(get_option('woocommerce_price_decimal_sep')), ENT_QUOTES);
        $thousands_sep = wp_specialchars_decode(stripslashes(get_option('woocommerce_price_thousand_sep')), ENT_QUOTES);

        $final_number = apply_filters('raw_woocommerce_price', floatval($final_number));
        $final_number = apply_filters('formatted_woocommerce_price', number_format($final_number, $num_decimals, $decimal_sep, $thousands_sep), $final_number, $num_decimals, $decimal_sep, $thousands_sep);

        if (apply_filters('woocommerce_price_trim_zeros', false) && $num_decimals > 0) {
            $final_number = wc_trim_zeros($final_number);
        }

        $final_number = $final_number . $suf . ' ';
        $final_number = ($negative ? '-' : '') . sprintf(get_woocommerce_price_format(), $currency_symbol, $final_number);
    }

    return $final_number;
}


function to_json($data) {
    $isArray = true;
    $keys = array_keys($data);
    $prevKey = -1;

    foreach ($keys as $key) {
        if (!is_numeric($key) || $prevKey + 1 != $key) {
            $isArray = false;
            break;
        } else {
            $prevKey++;
        }
    }

    unset($keys);
    $items = array();

    foreach ($data as $key => $value) {
        $item = (!$isArray ? "\"$key\":" : '');

        if (is_array($value)) {
            $item .= to_json($value);
        } elseif (is_null($value)) {
            $item .= 'null';
        } elseif (is_bool($value)) {
            $item .= $value ? 'true' : 'false';
        } elseif (is_string($value)) {
            $item .= '"' . preg_replace('%([\\x00-\\x1f\\x22\\x5c])%e', 'sprintf("\\\\u%04X", ord("$1"))', $value) . '"';
        } elseif (is_numeric($value)) {
            $item .= $value;
        } else {
            //throw new Exception('Wrong argument.');
        }
        $items[] = $item;
    }

    return ($isArray ? '[' : '{') . implode(',', $items) . ($isArray ? ']' : '}');
}

function log_me($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }

        error_log('Mobile Assistant LOG: ' . $message);
    }
}
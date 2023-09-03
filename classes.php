<?php
/**
* @Copyright ((c) plasma-web.ru
        * @license    GPLv2 or later
        */

namespace QuickForm;
\defined('QF3_VERSION') or die;

class Text
{
    public static function _($text)
    {
        static $loc = array();
        $lang = str_replace('_', '-', (string) \get_locale());

        if (\is_admin()) {
            $path = QF3_ADMIN_DIR . 'language/';
        } else {
            $path = QF3_PLUGIN_DIR . 'language/';
        }

        if (! isset($loc[$lang]) && file_exists($path . $lang . '/com_qf3.ini')) {
            $loc[$lang] = self::load($path . $lang . '/com_qf3.ini');
        }

        if (isset($loc[$lang]) && isset($loc[$lang][$text])) {
            return $loc[$lang][$text];
        }


        if (! isset($loc['en-GB'])) {
            $loc['en-GB'] = self::load($path . 'en-GB/com_qf3.ini');
        }

        if (isset($loc['en-GB'][$text])) {
            return $loc['en-GB'][$text];
        }

        return \__($text);
    }

    public static function load($file)
    {
        $arr = file($file, FILE_USE_INCLUDE_PATH|FILE_SKIP_EMPTY_LINES);
        $lng = [];
        foreach ($arr as $ar) {
            preg_match_all('/([\-_A-Z0-9]+) *= *"(.*)"$/', trim($ar), $m);
            if(isset($m[2][0])) {
                $lng[$m[1][0]] = $m[2][0];
            }
        }
        return $lng;
    }

    public static function translate($text) {
        return preg_replace_callback('/\b(QF_\w+)\b/', function ($m) {
            return self::_($m[1]);
        }, $text);
    }
}


class qf_config
{
    public function getItems($file) {
        if (file_exists($file)) {
            $data = json_decode(include $file);
            foreach ($data as $k => &$v) {
                $v = htmlspecialchars_decode($v, ENT_QUOTES);
            }
            return $data;
        }
    }

    protected function config($xml, $file) {
        $xml = simplexml_load_file($xml);
        $arr = $this->XMLToArray($xml);

        $config = (array) $this->getItems($file);
        if (! $config) {
            return $arr;
        }

        foreach ($arr as $key => $def) {
            if (! isset($config[$key])) {
                $config[$key] = $def;
            }
        }
        qf::gl('key', qf::get('cod', $config));

        return $config;
    }

    protected function XMLToArray($xml) {
        $arr = array();
        foreach ($xml as $k => $v) {
            if ((is_object($v) || is_array($v)) && ($k != 'field')) {
                $new = $this->XMLToArray($v);
                $arr = array_merge($arr, $new);
            } elseif ($k == 'field') {
                $name = isset($v['name']) ? (string)$v['name'] : '';
                $def = isset($v['default']) ? (string)$v['default'] : '';
                if ($name) {
                    $arr[$name]=$def;
                }
            }
        }
        return $arr;
    }

    public function getpath($fname = 'settings') {
        return QF3_ADMIN_DIR . 'src/configs/'.$fname.'.php';
    }

    public function get($name='', $fname='settings', $def='') {
        if (! $cfg = qf::gl($fname)) {
            $xml = QF3_ADMIN_DIR . 'forms/'.$fname.'.xml';
            $cfg = $this->config($xml, $this->getpath($fname));
            qf::gl($fname, $cfg);
        }
        return $name ? qf::get($name, $cfg, $def) : $cfg;
    }
}


class qf_session
{
    public function init()
    {
        if (!session_id()) {
            session_start();
        }

        \add_action('wp_logout', array( $this, 'endSession' ));
        \add_action('wp_login', array( $this, 'endSession' ));
    }

    public function endSession()
    {
        if (session_id()) {
            session_destroy();
        }
    }

    public function get($str = '', $def = false)
    {
        $ses = array();
        if(isset($_SESSION) && is_array($_SESSION)) {
            $ses = \sanitize_post($_SESSION, 'raw');
        }
        if ($str) {
            if (isset($ses[$str])) {
                return $ses[$str];
            } else {
                return $def;
            }
        }
        return $ses;
    }

    public function set($key, $val)
    {
        $_SESSION[$key] = $val;
    }

}

class qf_user
{
  public function get($str = '', $def = false)
  {
      if ($str == 'email') $str = 'user_email';
      elseif ($str == 'guest') return ! is_user_logged_in();
      elseif ($str == 'name') $str = 'display_name';

      $user = \wp_get_current_user();

      if ($str) {
          if (isset($user->$str)) {
              return $user->$str;
          } else {
              return $def;
          }
      }
      return $user;
  }

}


class qf
{
    public static function form($id) {
        require_once(QF3_PLUGIN_DIR . 'classes/buildform.php');
        $qf = new QuickForm();
        return $qf->getQuickForm($id);
    }

    public static function cart($headonly) {
        require_once(QF3_PLUGIN_DIR . 'classes/buildform.php');
        $qf = new QuickForm();
        return $qf->getShopModule($headonly);
    }

    public static function conf() {
        return new qf_config();
    }

    public static function ses() {
        return new qf_session();
    }

    public static function user() {
        return new qf_user();
    }

    public static function cmsVersion()
    {
        return 'w'.\get_bloginfo('version');
    }

    public static function addScript($type, $file) {
        if ($type == 'css') {
            \wp_enqueue_style($file, QF3_PLUGIN_URL . 'site/assets/' . $file, array(), QF3_VERSION);
        } elseif ($type == 'js') {
            \wp_enqueue_script($file, QF3_PLUGIN_URL . 'site/assets/' . $file, array(), QF3_VERSION);
        }
    }

    public static function getlang() {
        return str_replace('-', '_', (string) \get_locale());
    }

    public static function getacs() {
        return array(''=>'QF_ACCESS') + \wp_roles()->role_names;
    }


    public static function gettask($var = 'task', $def = '') {
        if (isset($_REQUEST[$var])) {
            return preg_replace('/[^a-z.\d\-_]/i', '', $_REQUEST[$var]);
        }
        return $def;
    }

    public static function gl($k, $v = false) {
        if($v !== false) {
            $GLOBALS['qf3'][$k] = $v;
        } else {
            return isset($GLOBALS['qf3'][$k]) ? $GLOBALS['qf3'][$k] : '';
        }
    }

    public static function getUrl() {
        return ((! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    public static function formatPrice($field, $math, $label='') {
        $arr = [0=>[',', ' '], 1=>['.', ','], 2=>['.', '']];
        $ar = $arr[(int) self::get('format', $field, 0)];
        $math = number_format($math, (int) self::get('fixed', $field, 0), $ar[0], $ar[1]);

        $label = $label ? '<span>'.$label.'</span>' : '';
        $math = '<span class="adderprice">'.$math.'</span>';
        $unit = '<span class="adderunit">'.self::get('unit', $field).'</span>';

        return $label.(self::get('pos', $field) ? $math.' '.$unit : $unit.' '.$math);
    }

    public static function get($v, $obj, $def = '') {
        $obj = (object) $obj;
        if (! isset($obj->$v)) {
            if (isset($obj->custom) && strpos($obj->custom, $v) !== false) {
                $pattern = "/".$v."\s*=\s*[\"]([^\"]*)[\"]\s?/i";
                preg_match($pattern, $obj->custom, $m);
                if (isset($m[1])) {
                    return $m[1];
                } else {
                    $subject = preg_replace("/\s*=\s*[\"]([^\"]*)[\"]\s?/i", '', $obj->custom);
                    if (strpos($subject, $v) !== false) {
                        return true;
                    } else {
                        return $def;
                    }
                }
            }
            return $def;
        }
        return $obj->$v;
    }


}

<?php
/**
* @Copyright ((c) plasma-web.ru
        * @license    GPLv2 or later
        */

namespace QuickForm;

\defined('QF3_VERSION') or die;


class qf_site
{
    public function __construct()
    {
        \add_action( 'plugins_loaded', array( $this, 'controll' ) );
        \add_shortcode('QF', array (&$this, 'show_form'));
        \add_shortcode('qfminicart', array (&$this, 'show_minicart'));
    }

    public function show_form($atts)
    {
        $id = (int) $atts['id'];
        if ($id) {
            return qf::form($id);
        }
    }

    public function show_minicart()
    {
        return qf::cart(false);
    }


    public function controll() {
        if(isset($_POST['option']) && ($_POST['option'] == 'com_qf3')) {
            qf::ses()->init();

            require_once(QF3_PLUGIN_DIR . 'controller.php');
            new controller();
        }

        if(isset($_COOKIE['qf3_message']) && $_COOKIE['qf3_message']) {
            qf::ses()->init();
            setcookie ("qf3_message", "", time() - 3600);
        }
    }


}

new qf_site();

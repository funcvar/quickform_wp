<?php
/**
* @Copyright ((c) plasma-web.ru
        * @license    GPLv2 or later
        */

namespace QuickForm;
\defined('QF3_VERSION') or die;

class qf_admin
{
    protected $messages=array();
    protected $errors=array();
    protected $closelink;
    protected $controller;

    public function __construct()
    {
        require_once(QF3_ADMIN_DIR . 'src/controller.php');
        $this->controller = new controller();

        \add_action('admin_menu', array( $this, 'register_menus' ));
        \add_action('admin_enqueue_scripts', array( $this, 'qfinline' ));
    }

    public function get($name, $obj, $def='')
    {
        $obj = (object) $obj;
        return isset($obj->$name) ? $obj->$name : $def;
    }

    public function addScript($type, $file)
    {
        if ($type == 'css') {
            \wp_enqueue_style($file, QF3_PLUGIN_URL . 'admin/assets/' . $file, array(), QF3_VERSION);
        } elseif ($type == 'js') {
            \wp_enqueue_script($file, QF3_PLUGIN_URL . 'admin/assets/' . $file, array(), QF3_VERSION);
        }
    }

    public function getmessages()
    {
        $mess = array();
        if ($this->errors) {
            $mess['err'] = $this->errors;
        }
        if ($this->messages) {
            $mess['mes'] = $this->messages;
        }
        return $mess;
    }

    public function getLanguages()
    {
        $new[''] =  'Language';
        $languages = \get_available_languages();
        require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        $translations = \wp_get_available_translations();
        foreach($languages as $language) {
            $lang = str_replace('-', '_', $language);
            $new[$lang] = $translations[$language]['native_name'];
        }
        return $new;
    }

    public function redirect($page)
    {
        header("Location: /wp-admin/admin.php?page=qf3-$page");
        exit;
    }

    public function register_menus()
    {
        \add_menu_page(
            Text::_('QF_PROJECT_MANAGER'),
            'QuickForm',
            'edit_pages',
            'qf3-projects',
            array( $this->controller, 'display' ),
            'data:image/svg+xml;base64,' . base64_encode('<svg id="light" enable-background="new 0 0 24 24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><g><path d="m22.5 6h-21c-.827 0-1.5-.673-1.5-1.5v-3c0-.827.673-1.5 1.5-1.5h21c.827 0 1.5.673 1.5 1.5v3c0 .827-.673 1.5-1.5 1.5zm-21-5c-.276 0-.5.224-.5.5v3c0 .276.224.5.5.5h21c.276 0 .5-.224.5-.5v-3c0-.276-.224-.5-.5-.5z" fill="#fff"/></g><g><path d="m9.5 24h-8c-.827 0-1.5-.673-1.5-1.5v-13c0-.827.673-1.5 1.5-1.5h8c.827 0 1.5.673 1.5 1.5v13c0 .827-.673 1.5-1.5 1.5zm-8-15c-.276 0-.5.224-.5.5v13c0 .276.224.5.5.5h8c.276 0 .5-.224.5-.5v-13c0-.276-.224-.5-.5-.5z" fill="#fff"/></g><g><path d="m22.5 15h-8c-.827 0-1.5-.673-1.5-1.5v-4c0-.827.673-1.5 1.5-1.5h8c.827 0 1.5.673 1.5 1.5v4c0 .827-.673 1.5-1.5 1.5zm-8-6c-.276 0-.5.224-.5.5v4c0 .276.224.5.5.5h8c.276 0 .5-.224.5-.5v-4c0-.276-.224-.5-.5-.5z" fill="#fff"/></g><g><path d="m22.5 24h-8c-.827 0-1.5-.673-1.5-1.5v-4c0-.827.673-1.5 1.5-1.5h8c.827 0 1.5.673 1.5 1.5v4c0 .827-.673 1.5-1.5 1.5zm-8-6c-.276 0-.5.224-.5.5v4c0 .276.224.5.5.5h8c.276 0 .5-.224.5-.5v-4c0-.276-.224-.5-.5-.5z" fill="#fff"/></g></svg>'),
            9
        );

        \add_submenu_page(
            'qf3-projects',
            Text::_('QF_PROJECT_MANAGER'),
            Text::_('QF_PROGECTS'),
            'edit_pages',
            'qf3-projects',
            array( $this->controller, 'display' )
        );

        if(qf::conf()->get('shopmod')) {
            \add_submenu_page(
                'qf3-projects',
                Text::_('QF_SHOP_SETTINGS'),
                Text::_('QF_SHOP_SETTINGS'),
                'edit_pages',
                'qf3-shop',
                array( $this->controller, 'display' )
            );
        }

        if(qf::conf()->get('filesmod')) {
            \add_submenu_page(
                'qf3-projects',
                Text::_('QF_ATTACHMENT_SETTINGS'),
                Text::_('QF_ATTACHMENT_SETTINGS'),
                'edit_pages',
                'qf3-attachment',
                array( $this->controller, 'display' )
            );
        }

        \add_submenu_page(
            'qf3-projects',
            Text::_('QF_GLOBAL_SETTINGS'),
            Text::_('QF_GLOBAL_SETTINGS'),
            'edit_pages',
            'qf3-settings',
            array( $this->controller, 'display' )
        );

        \add_submenu_page(
            'qf3-projects',
            Text::_('QF_EMAIL_HISTORY'),
            Text::_('QF_EMAIL_HISTORY'),
            'edit_pages',
            'qf3-historys',
            array( $this->controller, 'display' )
        );

        \add_submenu_page(
            'qf3-projects',
            Text::_('QF_PROJECT_MANAGER'),
            null,
            'edit_pages',
            'qf3-forms',
            array( $this->controller, 'display' )
        );

    }

    public function qfinline()
    {
        $styles = '
      	#toplevel_page_qf3-projects div.wp-menu-image.svg {
      		background-size: 16px auto;
      	}
      	';

        $key = 'qfinline';
        \wp_register_style($key, false, array(), true, true);
        \wp_add_inline_style($key, $styles);
        \wp_enqueue_style($key);
    }

    public function loadstyles() {
      $this->addScript('js', 'list.js');
      $this->addScript('css', 'list.css');
      \wp_enqueue_style('awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css');
    }

}

new qf_admin();

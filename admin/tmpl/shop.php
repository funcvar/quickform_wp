<?php
/**
* @Copyright ((c) plasma-web.ru
        * @license    GPLv2 or later
        */

namespace QuickForm;

\defined('QF3_VERSION') or die;

?>
<style>
    #wpwrap {
        background: #fff;
        padding-right: 20px;
        box-sizing: border-box;
    }
    #adminmenu, #adminmenu .wp-submenu, #adminmenuback, #adminmenuwrap {
        width: 0;
        overflow: hidden;
    }
    #wpcontent, #wpfooter {
        margin-left: 0;
    }
</style>

<div class="qf_form_style">
    <form method="post" name="qfadminform" class="formstyle">
        <div class="qfprojectpage">
            <div class="qftabs hor">
                <?php echo $this->form->renderForm(); ?>
            </div>
        </div>
    </form>
</div>

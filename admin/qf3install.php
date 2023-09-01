<?php
/**
* @Copyright ((c) plasma-web.ru
        * @license    GPLv2 or later
        */

namespace QuickForm;

\defined('QF3_VERSION') or die;

class qf_install
{
    public function install()
    {
        global $wpdb;

        $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."qf3_projects (
              id int(11) NOT NULL AUTO_INCREMENT,
              access char(20) NOT NULL default '',
              published tinyint(1) NOT NULL DEFAULT '1' ,
              title varchar(255) NOT NULL default '',
              params text,
              language char(20) NOT NULL default '',
              hits int(11) NOT NULL default '0',
              PRIMARY KEY  (id),
              KEY idx_access (access),
              KEY idx_state (published),
              KEY idx_language (language)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."qf3_forms (
              id int(11) NOT NULL AUTO_INCREMENT,
              title varchar(255) NOT NULL default '',
              fields text,
              projectid int(11) NOT NULL default '0',
              def tinyint(1) NOT NULL default '0',
              PRIMARY KEY  (id),
              KEY idx_projectid (projectid),
              KEY idx_def (def)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."qf3_ps (
              id int(11) NOT NULL AUTO_INCREMENT,
              st_title varchar(256) NOT NULL default '',
              st_formid int(11) NOT NULL default '0',
              st_form text,
              st_date varchar(50) NOT NULL default '',
              st_status tinyint(1) NOT NULL default '0',
              st_ip varchar(128) NOT NULL default '',
              st_user int(11) NOT NULL default '0',
              st_desk text,
              params text,
              PRIMARY KEY  (id),
              KEY idx_st_formid (st_formid)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci";
        $wpdb->query($sql);


        // creating examples

        $projects = (int) $wpdb->get_var("SELECT COUNT(id) FROM ".$wpdb->prefix."qf3_projects");
        $forms = (int) $wpdb->get_var("SELECT COUNT(id) FROM ".$wpdb->prefix."qf3_forms");

        if (! $projects && ! $forms) {

            // Example 1.

            $columns = array(
              'id' => 1,
              'title' => 'Example 3. The simplest calculator.',
              'params' => '{"cssform":"example3.css","jsform":"qf3.js","thnq_message":"Hi.","tmpl":"default","showtitle":1,"showurl":1,"calculatortype":"multipl","calcformula":"sv = {2.1}+0;\r\n2.2 = 2*3.14*{sv};\r\n2.3 = 3.14*{sv}*{sv};\r\n\r\ncv = {3.0}+0;\r\n3.1 = 4*{cv};\r\n3.2 = {cv}*{cv};\r\n\r\n1.3 = ({2.3}+{3.2})*{1.2}","history":1}'
            );

            $res = $wpdb->insert($wpdb->prefix.'qf3_projects', $columns, [ '%d', '%s', '%s' ]);
            if ($res) {
                $this->qfinsert(array(
                  'id' => 1,
                  'title' => 'main',
                  'fields' => '[{"teg":"input[radio]","fildnum":"1","orient":"1","label":"What do you calculate?","options":[{"related":"2","label":"Circle"},{"related":"3","label":"Square"}]},{"fildnum":4,"teg":"customHtml","label":"<div class=\"qf_centr\">at 1 euro per sq. m.</div>"},{"teg":"qf_number","fildnum":"2","orient":"1","custom":" min=\"0\" value=\"1\"","math":"v","label":"Quantity"},{"teg":"calculatorSum","fildnum":"3","unit":"€","fixed":"2","label":"Total cost:"}]',
                  'projectid' => 1,
                  'def' => 1
                ));

                $this->qfinsert(array(
                  'id' => 2,
                  'title' => 'Circle',
                  'fields' => '[{"teg":"qf_range","fildnum":"1","custom":"value=\"38\"","math":"v","label":"Circle radius, m"},{"fildnum":4,"teg":"customHtml","label":"<hr>"},{"teg":"calculatorSum","fildnum":"2","pos":"1","unit":"m","fixed":"1","format":"2","class":"","label":"Perimeter:"},{"teg":"calculatorSum","fildnum":"3","pos":"1","unit":"m<sup>2</sup>","fixed":"1","format":"2","label":"Circle area:"},{"fildnum":5,"teg":"customHtml","label":"<hr>"}]',
                  'projectid' => 1,
                  'def' => 0
                ));

                $this->qfinsert(array(
                  'id' => 3,
                  'title' => 'Square',
                  'fields' => '[{"teg":"qf_range","fildnum":"0","custom":"value=\"38\"","math":"v","label":"Square side, m"},{"fildnum":3,"teg":"customHtml","label":"<hr>"},{"teg":"calculatorSum","fildnum":"1","pos":"1","unit":"m","fixed":"1","format":"2","class":"","label":"Perimeter:"},{"teg":"calculatorSum","fildnum":"2","pos":"1","unit":"m<sup>2</sup>","fixed":"1","format":"2","label":"Square area:"},{"fildnum":4,"teg":"customHtml","label":"<hr>"}]',
                  'projectid' => 1,
                  'def' => 0
                ));

            }



            rename(QF3_DIR.'demo/example3.css', QF3_PLUGIN_DIR . 'assets/css/example3.css');
        }

        if(is_file(QF3_DIR.'demo/example3.css')) {
            unlink(QF3_DIR.'demo/example3.css');
        }
        if(is_dir(QF3_DIR.'demo')) {
            rmdir(QF3_DIR.'demo');
        }
    }


    protected function qfinsert($columns)
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'qf3_forms', $columns, [ '%d', '%s', '%s', '%d', '%d' ]);
    }


}

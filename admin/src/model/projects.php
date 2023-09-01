<?php
/**
* @Copyright ((c) plasma-web.ru
        * @license    GPLv2 or later
        */

namespace QuickForm;

\defined('QF3_VERSION') or die;

class projectsModel extends baseModel
{
    public $itemscount;

    public function __construct()
    {
        $this->closelink = 'projects';
    }

    public function getItems()
    {
        global $wpdb;

        $ses = qf::ses()->get('quickform', []);
        $filterdir = $this->get('filterdir', $ses, []);

        $order = $this->get('order', $filterdir, 'id');
        if(! in_array($order, array('published', 'title', 'hits', 'access', 'language'))) {
            $order = 'id';
        }
        $dir = $this->get('dir', $filterdir) == 'asc' ? 'asc' : 'desc';

        $limit = 12;

        if (! $filterdir) {
          $ses['filterdir'] = array('order'=>$order, 'dir'=>$dir);
          qf::ses()->set('quickform', $ses);
        }

        $filterlist = $this->get('filterlist', $ses, []);
        $where = array();

        foreach($filterlist as $k=>$v) {
            if(($v !== '') && $k == 'projects.published') $where[] = 'published='. (int) $v;
            elseif($v && $k == 'projects.language') $where[] = 'language="'. addslashes( $v ) . '"';
            elseif($v && $k == 'projects.access') $where[] = 'access="'. addslashes( $v ) . '"';
            elseif($v && $k == 'projects.search') {
                $v = '\'%'.addslashes(addcslashes(htmlspecialchars_decode(trim($v), ENT_QUOTES), '_%\\' )).'%\'';
                $where[] = '(title LIKE ' . $v . ' OR params LIKE ' . $v . ')';
            }
            elseif($v && $k == 'projects.limit') $limit = (int) $v;
        }

        $active = (int) $this->get('start', $_GET, 1);
        $start = ($active-1)*$limit;

        if($where) {
            $where = ' WHERE ' . implode(' AND ', $where);
        }
        else $where = '';

        $data = $wpdb->get_results( 'SELECT * FROM ' .$wpdb->prefix. 'qf3_projects' .$where. ' ORDER BY ' .$order. ' ' .$dir. ' LIMIT ' .$start. ',' .$limit );

        $this->itemscount = $wpdb->get_var( 'SELECT COUNT(*) FROM '.$wpdb->prefix. 'qf3_projects' .$where );

        return $data;
    }

    public function delete($page)
    {
        global $wpdb;
        $new_id = $this->checkcid();

        if($new_id) {
            $res1 = $wpdb->query( 'DELETE FROM '.$wpdb->prefix.'qf3_forms WHERE projectid IN ('.implode(',', $new_id).')' );
            $res2 = $wpdb->query( 'DELETE FROM '.$wpdb->prefix.'qf3_projects WHERE id IN ('.implode(',', $new_id).')' );

            if($res1 === false || ! $res2) $this->errors[] = 'QF_ERR_DATABASE';
            else $this->messages[] = $res1.' '.'QF_N_GROUPS_DELETED'.'; '.$res2.' '.'QF_N_PROJECTS_DELETED';

        }
    }

    public function importprojects() {
      if(! isset($_FILES['qf3file'])) return;
      $file = $_FILES['qf3file'];
      if($file['error']) return;
      if(! strpos($file['name'], '.qf3.zip')) return;

      $zip = new \ZipArchive;
      $res = $zip->open($file['tmp_name']);
      if ($res !== TRUE) return $this->error($res);
      $ser = str_replace('.','',$_SERVER['HTTP_HOST']);
      $keys = preg_split('//u', qf::gl( 'key'), null, PREG_SPLIT_NO_EMPTY);
      $projects = json_decode($zip->getFromName('projects.json'));
      $forms = json_decode($zip->getFromName('forms.json'));
      if (! $projects || ! $forms) return;
      if(qf::get('json', $projects[0]) == '1') {
        if(sizeof($keys) < 3) return;
        foreach($keys as $k) {
          if(strpos($ser, $k) === false) return;
        }
      }

      for($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);

        if (strpos($filename, 'import/') !== false) {
            $zip->extractTo(QF3_PLUGIN_DIR . 'assets/', $filename);
}
        elseif (substr($filename, -4) == '.css') {
          $zip->extractTo(QF3_PLUGIN_DIR . 'assets/css/', $filename);
        }
      }
      $zip->close();

      global $wpdb;
      $projId = [];
      foreach($projects as $project) {
        $inputData = array(
            'title' 	=> strip_tags($project->title),
            'id' 	=> 0,
            'published'    => (int) $project->published,
            'access'   => (int) $project->access,
            'language'   => $project->language,
            'hits'   => 0,
            'params'   => $project->params,
         );

         $wpdb->insert( $wpdb->prefix.'qf3_projects', $inputData);
         $projId[$project->id] = $wpdb->insert_id;
      }


      $formsId = [];
      foreach($forms as $form) {
        $projectid = $this->get($form->projectid, $projId, $form->projectid);
        $inputData = array(
            'title' 	=> strip_tags($form->title),
            'id' 	=> 0,
            'def' 	=> (int) $form->def,
            'projectid'   => (int) $projectid,
            'fields'   => $form->fields,
         );
         $wpdb->insert( $wpdb->prefix.'qf3_forms', $inputData);
         $formsId[$form->id] = $wpdb->insert_id;
      }

      $newforms = $wpdb->get_results( 'SELECT * FROM ' .$wpdb->prefix. 'qf3_forms WHERE id IN ('.implode(',', array_values($formsId)).')' );

      foreach ($newforms as $form) {
        $fields = preg_replace_callback('/"related":"(\d+)"/', function ($m) use ($formsId) {
          $m1 = (int) $m[1];
          if(isset($formsId[$m1])) return '"related":"'.$formsId[$m1].'"';
          else return '"related":"'.$m1.'"';
        }, $form->fields);
        $fields = preg_replace_callback('/data-project=\\\+"(\d+)\\\+"/', function ($m) use ($projId) {
          $m1 = (int) $m[1];
          if(isset($projId[$m1])) return str_replace($m1,$projId[$m1],$m[0]);
          else return $m[0];
        }, $fields);
        $fields = str_replace('components/com_qf3/assets', 'wp-content/plugins/quickform/site/assets', $fields);

          $inputData = array(
              'fields'   => $fields,
           );
          $wpdb->update( $wpdb->prefix.'qf3_forms', $inputData, array( 'id' => (int) $form->id ));
        }

        $calcformula = '';
        foreach ($projId as $k=>$v) {

          $params = $wpdb->get_var( 'SELECT params FROM '.$wpdb->prefix. 'qf3_projects WHERE id = '. (int) $v);
          $params = json_decode($params);

          if ($params->calcformula) {
            if ($params->calculatortype == 'custom') {
              $calcformula = preg_replace_callback('/\[(\'|")(\d+)(\.\d+)(\'|")\]/', function ($m) use ($formsId) {
                if(isset($formsId[$m[2]])) return '['.$m[1].$formsId[$m[2]].$m[3].$m[4].']';
              	else return '['.$m[1].$m[2].$m[3].$m[4].']';
              }, $params->calcformula);
            }
            else {
              $calcformula = preg_replace_callback('/{(\d+)(\.\d+)}/', function ($m) use ($formsId) {
                if(isset($formsId[$m[1]])) return '{'.$formsId[$m[1]].$m[2].'}';
              	else return '{'.$m[1].$m[2].'}';
              }, $params->calcformula);
              $calcformula = preg_replace_callback('/\b(\d+)(\.\d+\s?=)/', function ($m) use ($formsId) {
                if(isset($formsId[$m[1]])) return $formsId[$m[1]].$m[2];
              	else return $m[1].$m[2];
              }, $calcformula);
            }
            $params->calcformula = $calcformula;
            $params = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
            $inputData = array(
                'params'   => $params,
             );
            $wpdb->update( $wpdb->prefix.'qf3_projects', $inputData, array( 'id' => (int) $v ));

          }
        }



      return true;
    }

    public function import() {
      $html = '<form method="post" enctype="multipart/form-data"><input type="hidden" name="task" value="projects.importprojects"><input type="file" name="qf3file" accept=".qf3.zip"><input type="submit" value="ok."></form><span style="color:#9d2929">'.Text::_('QF_CSS_REWRITE').'</span>';
      echo $html;
    }

    public function export() {
      if(! extension_loaded('zip')) return $this->error("* Your php does not have a zip module");
      global $wpdb;
      $new_id = $this->checkcid();
      if(! $new_id) return $this->error("* No project IDs were specified for export.");
      $css = [];

      $projects = $wpdb->get_results( 'SELECT * FROM ' .$wpdb->prefix. 'qf3_projects WHERE id IN ('.implode(',', $new_id).')' );

      if(! $projects) return $this->error("* You don't have such projects.");
      foreach ($projects as $project) {
        $params = json_decode($project->params);
        if($params->cssform) {
          $cssfile = QF3_PLUGIN_DIR . 'assets/css/'.$params->cssform;
          if(is_file($cssfile)) $css[$params->cssform] = $cssfile;
        }
      }
      $projects = json_encode($projects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

      $forms = $wpdb->get_results( 'SELECT * FROM ' .$wpdb->prefix. 'qf3_forms WHERE projectid IN ('.implode(',', $new_id).')' );

      if(! $forms) return $this->error("* You don't have such forms.");
      $forms = json_encode($forms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

      $zip = new \ZipArchive();
      $zip_name = time().".qf3.zip";
      if($zip->open($zip_name, \ZIPARCHIVE::CREATE)!==TRUE) {
        return $this->error("* Sorry ZIP creation failed at this time");
      }

      $projects_file = tempnam(sys_get_temp_dir(), "qf");
      $handle = fopen($projects_file, "w");
      fwrite($handle, $projects, strlen($projects));
      fclose($handle);
      $zip->addFile($projects_file, 'projects.json');

      $forms_file = tempnam(sys_get_temp_dir(), "qf");
      $handle = fopen($forms_file, "w");
      fwrite($handle, $forms, strlen($forms));
      fclose($handle);
      $zip->addFile($forms_file, 'forms.json');

      foreach ($css as $k=>$file) {
        $zip->addFile($file, $k);
      }

      $zip->close();
      unlink($projects_file);
      unlink($forms_file);

      header('Content-type: application/zip');
      header('Content-Disposition: attachment; filename="'.$zip_name.'"');
      readfile($zip_name);
      unlink($zip_name);
      exit;
    }

    public function error($str)
    {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="error.txt";');
        echo $str;
        exit;
    }

}

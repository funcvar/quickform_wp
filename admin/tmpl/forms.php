<?php
/**
* @Copyright ((c) plasma-web.ru
        * @license    GPLv2 or later
        */

namespace QuickForm;

\defined('QF3_VERSION') or die;

?>
<div class="qfbreadcrumbs"><b><a href="/wp-admin/admin.php?page=qf3-projects"><?php echo Text::_('QF_ALL_PROJECTS'); ?></a> -> <a href="/wp-admin/admin.php?page=qf3-projects&task=project.edit&id=<?php echo (int) $_GET['projectid'] ?>"><?php echo $this->projectTitle; ?></a> -> <?php echo Text::_('QF_FIELD_GROUPS'); ?></b></div>

<form method="post" name="qfadminform" class="formstyle">
    <table class="qftable">
        <thead>
            <tr>
                <th><input autocomplete="off" type="checkbox" name="cid[]" onclick="QFlist.checkAll(this)"></th>
                <th><?php echo $this->filterdir('def', 'QF_BY_DEFAULT') ?></th>
                <th class="qftitle"><?php echo $this->filterdir('title', 'Title') ?></th>
                <th><?php echo $this->filterdir('id', 'ID') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php echo $this->items; ?>
     </tbody>
    </table>
    <input type="hidden" name="projectid" value="<?php echo (int) $_GET['projectid'] ?>" />
</form>

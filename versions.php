<?php
/*------------------------------------------------------------------------
# plg_content_versions - rjVersions plugin
# ------------------------------------------------------------------------
# author    Ronald J. de Vries
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: http://www.rjdev.nl
# Technical Support:  Forum - http://www.rjdev.nl
-------------------------------------------------------------------------*/
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Import library dependencies
jimport('joomla.event.plugin');

class plgContentVersions extends JPlugin
{

    public function __construct(&$subject, $config = array()) {

        // Load the language files
	parent::__construct($subject, $config);
        $this->loadLanguage();

    }

    public function makeCopy ($id) {
        
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('*')
              ->from('#__content')
              ->where('id = '.(int)$id);
        $db->setQuery($query);
        $db->Query($query);
        $dbcontent = $db->loadObject();
	// Set modified & modified_by to created & created_by when modified_by is 0
        // This is needed because com_versions/views/versions/view.html.php getVersions queries on modified & modified_by
        // to get the username of the user that made the article or modified it.
        if($dbcontent->modified_by == 0) {
            $dbcontent->modified = $dbcontent->created;
            $dbcontent->modified_by = $dbcontent->created_by;
        }
        $db->insertObject('#__versions', $dbcontent);
        
    }
    
    public function onContentBeforeSave($context, &$article, $isNew) {
        
        /*
         * 1. Compair the current article content against the content in the editor.
         *    Do nothing when there are no changes
         * 2. Make a copy of the current version if the article is not new.
         *    $isNew returns a 1 if it is a new article
         * 3. Clean-Up if there are more versions than the version_limit.
         */

        // Get content from the database
        $id = (int)$article->id;
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('\'introtext\', \'fulltext\'')
              ->from('#__content')
              ->where('id = '.(int) $id);
        $db->setQuery($query);
        $db->Query($query);
        $dbcontent = $db->loadRow();
        $dbcontent = $dbcontent['0'].$dbcontent['1'];
        
        // Get content from the editor
        $edcontent = $article->introtext.$article->fulltext;
        
        // Compair the content from the database with the content from the editor
        // Exit the function by no changes
        if(strcmp($dbcontent, $edcontent) == 0) { return 0; }
             
        // Make Copy
        if(($article->id > 0) || (!$isNew)){ $this->makeCopy($id); }

        // Clean-Up
        $version_limit = $this->params->def('version_limit');

        // Create temp table.
        // Create is not supported in the JDatabaseQuery class.
        // And "CREATE TEMPORARY" (mysql) is not compatible with other sql-servers like Oracle and MSSQL.
        // Therefor we have to make a new table and drop it at the end.
        $sql = "CREATE TABLE #__versions_tmp (vid INT(10))";
        $db->setQuery($sql);
        $db->Query($sql);

        // Put latest id's into the temp table
        // Select them
        $query = $db->getQuery(true);
        $query->select('vid')
              ->from('#__versions')
              ->where('id = '.(int)$id)
              ->order('vid DESC LIMIT '.(int)$version_limit);
        $db->setQuery($query);
        $db->Query($query);
        $dbcontent = $db->loadResultArray();
        // Insert them
        $tuples = array();
        foreach($dbcontent as $vid) {
            $tuples[] = '('.$vid.')';
        }
        $query = $db->getQuery(true);
        $query->insert('#__versions_tmp');
        $query->values($tuples);
        $db->setQuery($query);
        $db->Query($query);

        // Delete all rows from #__versions where id is not in #__versions_tmp table
        // Select rows
        $query = $db->getQuery(true);
        $query->select('vid')
              ->from('#__versions_tmp');
        $db->setQuery($query);
        $db->Query($query);
        $dbvid = $db->loadResultArray();

        // Delete rows
        if(!empty ($dbvid[0])) { // When the versions table is empty the 'where' fails and it would not be needed to delete.
            $query = $db->getQuery(true);
            $query->delete('#__versions');
            foreach ($dbvid as $vid) {
                $query->where('vid NOT IN ('.$vid.')');
            }
            $db->setQuery($query);
            $db->Query($query);
	}

        // Drop temp table
        $sql = "DROP TABLE #__versions_tmp";
        $db->setQuery($sql);
        $db->Query($sql);
        return true;

    }

    public function onContentBeforeDelete($context, $data) {

        /*
         * Make a copy before deletion.
         * Delete all versions except the last one.
         */

        // Start database instance.
        $db =& JFactory::getDBO();
        $id = $data->id;
        if($id > 0) {

            // Make copy before deletion
            $this->makeCopy($id);

            // Only save the last version. Delete the other versions
            $query = $db->getQuery(true);
            $query->from('#__versions');
            $query->delete();
            $query->where('state != \'-2\'');
            $query->where('id = '.(int)$id);
            $db->setQuery($query);
            $db->Query($query);
            
        }

        return true;

    }

}

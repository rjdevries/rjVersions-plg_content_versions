<?php
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

    public function onContentBeforeSave($context, &$article, $isNew) {
        /*
         * 1. Compair the current article content against the content in the editor.
         *    Do nothing when there are no changes
         * 2. Make a copy of the current version if the article is not new.
         *    $isNew returns a 1 if it is a new article
         * 3. Clean-Up if there are more versions than the version_limit.
         */

        // Start database instance.
        $db =& JFactory::getDBO();


        // Get content from the database
        $contentId = (int)$article->id;
        $sql = "SELECT `introtext` , `fulltext` FROM #__content WHERE `id` = $contentId";
        $db->setQuery($sql);
        $db->Query($sql);
        $dbcontent = $db->loadRow();
        $dbcontent = $dbcontent['0'].$dbcontent['1'];
        
        // Get content from the editor
        $edcontent = $article->introtext.$article->fulltext;
        
        // Compair the content from the database with the content from the editor
        // Exit the function by no changes
        if(strcmp($dbcontent, $edcontent) == 0) { return 0; }
             

        // Make Copy
        if(($article->id > 0) || (!$isNew)){

            $contentId = (int)$article->id;
            $sql = "INSERT INTO #__versions (`content_id` , `asset_id` , `title` , `alias` , `title_alias` , `introtext` , `fulltext` , `state` , `sectionid` , `mask` , `catid` , `created` , `created_by` , `created_by_alias` , `modified` , `modified_by` , `checked_out` , `checked_out_time` , `publish_up` , `publish_down` , `images` , `urls` , `attribs` , `version` , `parentid` , `ordering` , `metakey` , `metadesc` , `access` , `hits` , `metadata` , `featured` , `language` , `xreference`)
                    SELECT `id` , `asset_id` , `title` , `alias` , `title_alias` , `introtext` , `fulltext` , `state` , `sectionid` , `mask` , `catid` , `created` , `created_by` , `created_by_alias` , `modified` , `modified_by` , `checked_out` , `checked_out_time` , `publish_up` , `publish_down` , `images` , `urls` , `attribs` , `version` , `parentid` , `ordering` , `metakey` , `metadesc` , `access` , `hits` , `metadata` , `featured` , `language` , `xreference`
                    FROM #__content WHERE `id` = $contentId";
            $db->setQuery($sql);
            $db->Query($sql);

        }


        // Clean-Up
        $version_limit = $this->params->def('version_limit');

        // Create temp table
        $sql = "CREATE TEMPORARY TABLE #__versions_tmp (id INT(10))";
        $db->setQuery($sql);
        $db->Query($sql);

        // Put latest id's into the temp table
        $sql = "INSERT INTO #__versions_tmp (`id`) SELECT `id` FROM #__versions WHERE `content_id` = $contentId order by `id` DESC LIMIT $version_limit";
        $db->setQuery($sql);
        $db->Query($sql);

        // Delete all rows from #__versions where id is not in temp table
        $sql = "DELETE FROM #__versions WHERE `id` NOT IN (SELECT `id` FROM #__versions_tmp)";
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

        $contentId = $data->id;
        if($contentId > 0) {

            // Make Copy
            $sql = "INSERT INTO #__versions (`content_id` , `asset_id` , `title` , `alias` , `title_alias` , `introtext` , `fulltext` , `state` , `sectionid` , `mask` , `catid` , `created` , `created_by` , `created_by_alias` , `modified` , `modified_by` , `checked_out` , `checked_out_time` , `publish_up` , `publish_down` , `images` , `urls` , `attribs` , `version` , `parentid` , `ordering` , `metakey` , `metadesc` , `access` , `hits` , `metadata` , `featured` , `language` , `xreference`)
                    SELECT `id` , `asset_id` , `title` , `alias` , `title_alias` , `introtext` , `fulltext` , `state` , `sectionid` , `mask` , `catid` , `created` , `created_by` , `created_by_alias` , `modified` , `modified_by` , `checked_out` , `checked_out_time` , `publish_up` , `publish_down` , `images` , `urls` , `attribs` , `version` , `parentid` , `ordering` , `metakey` , `metadesc` , `access` , `hits` , `metadata` , `featured` , `language` , `xreference`
                    FROM #__content WHERE `id` = $contentId";
            $db->setQuery($sql);
            $db->Query($sql);

            // Set versions state
            $sql = "DELETE FROM #__versions WHERE `state` != '-2' AND `content_id` = $contentId";
            $db->setQuery($sql);
            $db->Query($sql);
            
        }

        return true;

    }

}
<?php

	Class extension_pagesfield extends Extension{
	
		public function about(){
			return array('name' => 'Field: Page Select Box',
						 'version' => '1.4',
						 'release-date' => '2010-07-15',
						 'author' => array('name' => 'Symphony Team',
										   'website' => 'http://www.symphony21.com',
										   'email' => 'team@symphony21.com')
				 		);
		}
		
		public function uninstall(){
			$this->_Parent->Database->query("DROP TABLE `tbl_fields_pages`");
		}


		public function install(){

			return $this->_Parent->Database->query("CREATE TABLE `tbl_fields_pages` (
			  `id` int(11) unsigned NOT NULL auto_increment,
			  `field_id` int(11) unsigned NOT NULL,
			  `allow_multiple_selection` enum('yes','no') NOT NULL default 'no',
			  `page_types` varchar(255) default NULL,
			  PRIMARY KEY  (`id`),
			  UNIQUE KEY `field_id` (`field_id`)
			) TYPE=MyISAM");

		}
		
		public function update($previousVersion) {
			if(version_compare($previousVersion, '1.3', '<')){
				$updated = Administration::instance()->Database->query(
					"ALTER TABLE `tbl_fields_pages`
						ADD `page_types` varchar(255) default NULL"
				);
				if(!$updated) return false;
			}
			return true;
		}
		
	}


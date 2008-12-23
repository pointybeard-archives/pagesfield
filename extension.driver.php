<?php

	Class extension_pagesfield extends Extension{
	
		public function about(){
			return array('name' => 'Field: Page Select Box',
						 'version' => '1.2',
						 'release-date' => '2008-12-23',
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
			  PRIMARY KEY  (`id`),
			  UNIQUE KEY `field_id` (`field_id`)
			) TYPE=MyISAM");

		}
			
	}


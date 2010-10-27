<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	Class fieldPages extends Field{
		
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Page Select Box';
			
			// Set default
			$this->set('show_column', 'no');			
		}

		function canToggle(){
			return ($this->get('allow_multiple_selection') == 'yes' ? false : true);
		}
		
		function allowDatasourceOutputGrouping(){
			## Grouping follows the same rule as toggling.
			return $this->canToggle();
		}
		
		function allowDatasourceParamOutput(){
			return true;
		}		
		
		function canFilter(){
			return true;
		}
		
		function canPrePopulate(){
			return true;
		}	

		function isSortable(){
			return true;
		}
		
		function appendFormattedElement(&$wrapper, $data, $encode=false){

			if(!is_array($data) || empty($data)) return;
			
			$list = new XMLElement($this->get('element_name'));
			
			if(!is_array($data['handle'])) $data['handle'] = array($data['handle']);
			if(!is_array($data['page_id'])) $data['page_id'] = array($data['page_id']);
			if(!is_array($data['title'])) $data['title'] = array($data['title']);
			
			for($ii = 0; $ii < count($data['handle']); $ii++){
				$list->appendChild(new XMLElement('page', General::sanitize($data['title'][$ii]), array('handle' => $data['handle'][$ii], 'id' => $data['page_id'][$ii])));
			}

			$wrapper->appendChild($list);
		}		
			
		function getToggleStates($include_parent_titles=true){
			
			$types = $this->get('page_types');
			$types = preg_split('/\s*,\s*/', $types, -1, PREG_SPLIT_NO_EMPTY);
			$types = @array_map('trim', $types);
			if (is_array($types) && !empty($types)) {
				$where = "";
				for ($i=0; $i < sizeof($types); $i++) {
					if ($i==0) {
						$where .= "t.type = '{$types[$i]}'";
					} else {
						$where .= " OR t.type = '{$types[$i]}'";
					}
				}
				$where = "AND ({$where})";
			} else {
				$where = NULL;
			}
			
			$pages = $this->_engine->Database->fetch("
				SELECT
					p.*
				FROM
					`tbl_pages` AS p
				LEFT JOIN
					`tbl_pages_types` as t ON p.id = t.page_id
				WHERE 1
				{$where}
				ORDER BY
					p.sortorder ASC
			");
			
			$result = array();
			foreach($pages as $p){
				
				$title = $p['title'];
				
				if($p['path'] != NULL && $include_parent_titles){
					$bits = preg_split('/\//', $p['path'], -1, PREG_SPLIT_NO_EMPTY);
					$bits = array_reverse($bits);
					
					foreach($bits as $h){
						$parent = $this->_engine->Database->fetchVar('title', 0, "SELECT `title` FROM `tbl_pages` WHERE `handle` = '$h' LIMIT 1");
						$title = $parent . ' / ' . $title;
					}
				}
				
				$result[$p['id']] = $title;
			}
			
			return $result;
		}
		
		function toggleFieldData($data, $newState){
			
			$page = $this->_engine->Database->fetchRow(0, "SELECT `title`, `id`, `handle` FROM `tbl_pages` WHERE `id` = '$newState' LIMIT 1");
			
			$data['handle'] = $page['handle'];
			$data['title'] = $page['title'];
			$data['page_id'] = $page['id'];
			
			return $data;
		}
		
		function __sortTitlesAscending($t1, $t2){
			return strcmp(strtolower($t1[2]), strtolower($t2[2]));
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			$states = $this->getToggleStates();
			
			if(!is_array($data['handle'])) $data['handle'] = array($data['handle']);
			if(!is_array($data['page_id'])) $data['page_id'] = array($data['page_id']);
			if(!is_array($data['title'])) $data['title'] = array($data['title']);
			
			$options = array();

			foreach($states as $id => $title){
				$options[] = array($id, in_array($id, $data['page_id']), General::sanitize($title));
			}
			
			usort($options, array('fieldPages','__sortTitlesAscending'));
			
			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix;
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';
			
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options, ($this->get('allow_multiple_selection') == 'yes' ? array('multiple' => 'multiple') : NULL)));
			
			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);		
		}

		function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			
			parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);
			
			$data = preg_split('/,\s*/i', $data);
			$data = array_map('trim', $data);
			
			$existing_options = $this->getToggleStates(false);

			if(is_array($existing_options) && !empty($existing_options)){
				$optionlist = new XMLElement('ul');
				$optionlist->setAttribute('class', 'tags');
				
				foreach($existing_options as $option) $optionlist->appendChild(new XMLElement('li', $option));
						
				$wrapper->appendChild($optionlist);
			}
					
		}

		function prepareTableValue($data, XMLElement $link=NULL){
			// $value = $data['title'];
			
			// Recursive get the parents:			
			$value = $this->getParents($data['page_id']);
			
			if(!is_array($value)) $value = array($value);
			
			return parent::prepareTableValue(array('value' => General::sanitize(@implode(', ', $value))), $link);
		}
		
		function getParents($pageId)
		{
			$sql = 'SELECT `title`, `parent` FROM `tbl_pages` WHERE `id` = '.$pageId.';';
			$arr = Symphony::Database()->fetch($sql);
			
			if(count($arr) == 1)
			{
				// Recursive:
				if($arr[0]['parent'] != null)
				{
					return $this->getParents($arr[0]['parent']).' / '.$arr[0]['title'];
				} else {
					return $arr[0]['title'];
				}
			}
			
			// By default, return an empty string:
			return '';
		}

		function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){

			$status = self::__OK__;
			
			if(empty($data)) return NULL;
			
			if(!is_array($data)) $data = array($data);

			$result = array('title' => array(), 'handle' => array(), 'page_id' => array());
			foreach($data as $page_id){
				
				$page = $this->_engine->Database->fetchRow(0, "SELECT `title`, `handle` FROM `tbl_pages` WHERE `id` = '$page_id' LIMIT 1");
				 
				$result['handle'][] = $page['handle'];
				$result['title'][] = $page['title'];
				$result['page_id'][] = $page_id;
			}

			return $result;
		}

		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){
			
			$field_id = $this->get('id');
			
			if(self::isFilterRegex($data[0])):
				
				$pattern = str_replace('regexp:', '', $data[0]);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (`t$field_id`.title REGEXP '$pattern' OR `t$field_id`.handle REGEXP '$pattern') ";

			
			elseif($andOperation):
			
				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND (`t$field_id$key`.page_id = '$bit' OR `t$field_id$key`.handle = '$bit' OR `t$field_id$key`.title = '$bit') ";
				}
							
			else:
			
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (`t$field_id`.page_id IN ('".@implode("', '", $data)."') OR `t$field_id`.handle IN ('".@implode("', '", $data)."') OR `t$field_id`.title IN ('".@implode("', '", $data)."')) ";
						
			endif;
			
			return true;
			
		}
		
		function commit(){
			
			if(!parent::commit()) return false;
			
			$id = $this->get('id');
			$page_types = $this->get('page_types'); // TODO safe

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			$fields['allow_multiple_selection'] = ($this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no');
			$fields['page_types'] = $page_types;

			$this->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			
			if(!$this->Database->insert($fields, 'tbl_fields_' . $this->handle())) return false;
			
			return true;
					
		}
		
		function findDefaults(&$fields){
			if(!isset($fields['allow_multiple_selection'])) $fields['allow_multiple_selection'] = 'no';
		}
				
		function displaySettingsPanel(&$wrapper, $errors=NULL){		
			
			parent::displaySettingsPanel($wrapper, $errors);
						
			## Page types filter
			$label = new XMLElement('label', __('Filter pages by type'));
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][page_types]', $this->get('page_types')));
			$wrapper->appendChild($label);
			$types = Symphony::Database()->fetchCol('type', "
				SELECT
					p.type
				FROM
					`tbl_pages_types` AS p
				GROUP BY
					p.type
				ORDER BY
					p.type ASC
			");
			$tags = new XMLElement('ul');
			$tags->setAttribute('class', 'tags');
			if(is_array($types) && !empty($types)) {
				foreach($types as $type) $tags->appendChild(new XMLElement('li', $type));
			}
			$wrapper->appendChild($tags);
			
			## Allow selection of multiple items
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') $input->setAttribute('checked', 'checked');			
			$label->setValue($input->generate() . ' Allow selection of multiple pages');
			$wrapper->appendChild($label);
			
			$this->appendShowColumnCheckbox($wrapper);
						
		}

		function groupRecords($records){
			
			if(!is_array($records) || empty($records)) return;
			
			$groups = array($this->get('element_name') => array());
			
			foreach($records as $r){
				$data = $r->getData($this->get('id'));
				
				$handle = $data['handle'];
				
				if(!isset($groups[$this->get('element_name')][$handle])){
					$groups[$this->get('element_name')][$handle] = array('attr' => array('handle' => $handle, 'name' => General::sanitize($data['title'])),
																		 'records' => array(), 'groups' => array());
				}	
																					
				$groups[$this->get('element_name')][$handle]['records'][] = $r;
								
			}

			return $groups;
		}
		
		function createTable(){
			
			return $this->_engine->Database->query(
			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `page_id` int(11) unsigned NOT NULL,
				  `title` varchar(255) default NULL,
				  `handle` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `handle` (`handle`),
				  KEY `page_id` (`page_id`)
				);"
			
			);
		}
		
		public function getExampleFormMarkup(){
			$states = $this->getToggleStates();
			
			$options = array();

			foreach($states as $handle => $v){
				$options[] = array($handle, NULL, $v);
			}
			
			$fieldname = 'fields['.$this->get('element_name').']';
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';
			
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options, ($this->get('allow_multiple_selection') == 'yes' ? array('multiple' => 'multiple') : NULL)));
			
			return $label;
		}			

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC', $useIDFieldForSorting=false){
			
			
			$sort_field = (!$useIDFieldForSorting ? 'ed' : 't' . $this->get('id'));
			
			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `$sort_field` ON (`e`.`id` = `$sort_field`.`entry_id`) ";
			$joins .= "INNER JOIN `tbl_pages` AS `p1` ON (`$sort_field`.`page_id` = `p1`.`id`) ";
			$joins .= "LEFT JOIN `tbl_pages` AS `p2` ON (`p1`.`parent` = `p2`.`id`) ";
			
			$sort .= 'ORDER BY `p2`.`handle` '.$order.', ' . (strtolower($order) == 'random' ? 'RAND()' : "`$sort_field`.`handle` $order");
		}

	}


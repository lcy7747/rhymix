<?php
    /**
     * @class  autoinstallAdminView
     * @author sol (sol@ngleader.com)
     * @brief  autoinstall 모듈의 admin view class
     **/


    class autoinstallAdminView extends autoinstall {


	    function init() {
		    $template_path = sprintf("%stpl/",$this->module_path);
            Context::set('original_site', $this->original_site);
            Context::set('uri', $this->uri);
		    $this->setTemplatePath($template_path);

            $ftp_info =  Context::getFTPInfo();
            if(!$ftp_info->ftp_root_path) Context::set('show_ftp_note', true);

            $this->dispCategory();
            $oModel = &getModel('autoinstall');
            Context::set('tCount', $oModel->getPackageCount(null));
            Context::set('iCount', $oModel->getInstalledPackageCount());
	    }

        function rearrange(&$item, &$targets)
        {
            $ret = null;
            foreach($targets as $target)
            {
                $ret->{$target} = $item->{$target}->body;
            }
            return $ret;
        }

        function rearranges($items, $packages = null)
        {
            if(!is_array($items)) $items = array($items);
            $item_list = array();
            $targets = array('category_srl', 'package_srl', 'item_screenshot_url', 'package_voted', 'package_voter', 'package_description', 'package_downloaded', 'item_regdate', 'title', 'item_version', 'package_star');
            $targetpackages = array();
            foreach($items as $item)
            {
                $targetpackages[$item->package_srl->body] = 0;
            }
            $oModel = &getModel('autoinstall');
            if($package == null)
                $packages = $oModel->getInstalledPackages(array_keys($targetpackages));

            foreach($items as $item)
            {
                $v = $this->rearrange($item, $targets);
                if($packages[$v->package_srl])
                {
                    $v->current_version = $packages[$v->package_srl]->current_version;
                    $v->need_update = $packages[$v->package_srl]->need_update;
                }
                $item_list[$v->package_srl] = $v;
            }

            return $item_list;
        }

        function dispAutoinstallAdminInstalledPackages()
        {
            $page = Context::get('page');
            if(!$page) $page = 1; 
            Context::set('page', $page);
            $oModel = &getModel('autoinstall');
            $output = $oModel->getInstalledPackageList($page);
            $package_list = $output->data;

            $params["act"] = "getResourceapiPackages";
            $params["package_srls"] = implode(",", array_keys($package_list)); 
            $body = XmlGenerater::generate($params);
            $buff = FileHandler::getRemoteResource($this->uri, $body, 3, "POST", "application/xml");
            $xml_lUpdate = new XmlParser();
            $xmlDoc = $xml_lUpdate->parse($buff);
            if($xmlDoc && $xmlDoc->response->packagelist->item)
            {
                $item_list = $this->rearranges($xmlDoc->response->packagelist->item, $package_list);
                $res = array();
                foreach($package_list as $package_srl => $package)
                {
                    $res[] = $item_list[$package_srl];
                }
                Context::set('item_list', $res); 
            }
            Context::set('page_navigation', $output->page_navigation);

            $this->setTemplateFile('index'); 
        }

        function dispAutoinstallAdminInstall() {
            $package_srl = Context::get('package_srl');
            if(!$package_srl) return $this->dispAutoinstallAdminIndex();

            $params["act"] = "getResourceapiInstallInfo";
            $params["package_srl"] = $package_srl;
            $xmlDoc = XmlGenerater::getXmlDoc($params);
            $oModel = &getModel('autoinstall');

            $targetpackages = array();
            if($xmlDoc)
            {
                $xmlPackage =& $xmlDoc->response->package;
                $package->package_srl = $xmlPackage->package_srl->body;
                $package->title = $xmlPackage->title->body;
                $package->package_description = $xmlPackage->package_description->body;
                $package->version = $xmlPackage->version->body;
                $package->path = $xmlPackage->path->body;
                if($xmlPackage->depends)
                {
                    if(!is_array($xmlPackage->depends->item)) $xmlPackage->depends->item = array($xmlPackage->depends->item);
                    $package->depends = array();
                    foreach($xmlPackage->depends->item as $item)
                    {
                        $dep_item = null;
                        $dep_item->package_srl = $item->package_srl->body;
                        $dep_item->title = $item->title->body;
                        $dep_item->version = $item->version->body;
                        $dep_item->path = $item->path->body;
                        $package->depends[] = $dep_item;
                        $targetpackages[$dep_item->package_srl] = 1;
                    }
                    $packages = $oModel->getInstalledPackages(array_keys($targetpackages));
                    $package->deplist = "";
                    foreach($package->depends as $key => $dep)
                    {
                        if(!$packages[$dep->package_srl]) {
                            $package->depends[$key]->installed = false;
                            $package->package_srl .= ",". $dep->package_srl;
                        }
                        else {
                            $package->depends[$key]->installed = true;
                            $package->depends[$key]->cur_version = $packages[$dep->package_srl]->current_version;
                            if(version_compare($dep->version, $packages[$dep->package_srl]->current_version, ">"))
                            {
                                $package->depends[$key]->need_update = true;
                                $package->package_srl .= ",". $dep->package_srl;
                            }
                            else
                            {
                                $package->need_update = false;
                            }
                        }
                    }
                }
                $installedPackage = $oModel->getInstalledPackage($package_srl);
                if($installedPackage) {
                    $package->installed = true;
                    $package->cur_version = $installedPackage->current_version;
                    $package->need_update = version_compare($package->version, $installedPackage->current_version, ">");
                }

                Context::set("package", $package);
            }
            $this->setTemplateFile('install');
        }

        function dispAutoinstallAdminIndex() {
            $oModuleModel = &getModel('module');
			$config = $oModuleModel->getModuleConfig('autoinstall');
            $ftp_info =  Context::getFTPInfo();
            if(!$ftp_info->ftp_root_path) Context::set('show_ftp_note', true);

            $this->setTemplateFile('index');

            $params = array();
            $params["act"] = "getResourceapiLastupdate";
            $body = XmlGenerater::generate($params);
            $buff = FileHandler::getRemoteResource($this->uri, $body, 3, "POST", "application/xml");
            $xml_lUpdate = new XmlParser();
            $lUpdateDoc = $xml_lUpdate->parse($buff);
            $updateDate = $lUpdateDoc->response->updatedate->body;

            $oModel = &getModel('autoinstall');
            $item = $oModel->getLatestPackage();
            if(!$item || $item->updatedate < $updateDate )
            {
                Context::set('need_update', true); 
                return;
            }


            $page = Context::get('page');
            if(!$page) $page = 1; 
            Context::set('page', $page);

            $order_type = Context::get('order_type');
            if(!in_array($order_type, array('asc', 'desc'))) $order_type = 'desc';
            Context::set('order_type', $order_type);

            $order_target = Context::get('order_target');
            if(!in_array($order_target, array('newest', 'download', 'popular'))) $order_target = 'newest';
            Context::set('order_target', $order_target);

            $search_keyword = Context::get('search_keyword');

            $childrenList = Context::get('childrenList');
            $category_srl = Context::get('category_srl');
            if($childrenList) $params["category_srl"] = $childrenList;
            else if($category_srl) $params["category_srl"] = $category_srl;

            $params["act"] = "getResourceapiPackagelist";
            $params["order_target"] = $order_target;
            $params["order_type"] = $order_type;
            $params["page"] = $page;
            if($search_keyword)
            {
                $params["search_keyword"] = $search_keyword;
            }
            $xmlDoc = XmlGenerater::getXmlDoc($params); 
            if($xmlDoc && $xmlDoc->response->packagelist->item)
            {
                $item_list = $this->rearranges($xmlDoc->response->packagelist->item);
                Context::set('item_list', $item_list); 
                $array = array('total_count', 'total_page', 'cur_page', 'page_count', 'first_page', 'last_page');
                $page_nav = $this->rearrange($xmlDoc->response->page_navigation, $array); 
                $page_navigation = new PageHandler($page_nav->total_count, $page_nav->total_page, $page_nav->cur_page, $page_nav->page_count);
                Context::set('page_navigation', $page_navigation);
            }

        }

        function dispCategory()
        {
            $oModel = &getModel('autoinstall');
            $categories = &$oModel->getCategoryList();
            Context::set('categories', $categories);
        }
    }
?>

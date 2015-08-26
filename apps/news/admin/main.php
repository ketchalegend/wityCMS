<?php
/**
 * News Application - Admin Controller
 */

defined('WITYCMS_VERSION') or die('Access denied');

/**
 * NewsAdminController is the Admin Controller of the News Application
 * 
 * @package Apps\News\Admin
 * @author Johan Dufau <johan.dufau@creatiwity.net>
 * @author Julien Blatecky <julien.blatecky@creatiwity.net>
 * @version 0.5.0-dev-24-03-2015
 */
class NewsAdminController extends WController {
	private $upload_dir;

	public function __construct() {
		$this->upload_dir = WITY_PATH.'upload'.DS.'news'.DS;
	}
	
	/**
	 * Handles news action
	 */
	protected function news(array $params) {
		$n = 30; // Rows per page
		$sort_by = '';
		$sens = '';
		$page = 1;
		
		// Sorting criterias given by URL
		if (isset($params[0])) {
			$criterias = $params[0];
			sscanf(str_replace('-', ' ', $criterias), '%s %s %d', $sort_by_crit, $sens, $page_crit);
			
			if (in_array($sort_by_crit, array('id', 'title', 'author', 'created_date', 'views'))) {
				$sort_by = $sort_by_crit;
			}
			
			if ($page_crit > 1) {
				$page = $page_crit;
			}
		}
		
		// SortingHelper
		$sortingHelper = WHelper::load('SortingHelper', array(
			array('id', 'title', 'author', 'created_date', 'views'), 
			'created_date', 'DESC'
		));
		$sort = $sortingHelper->findSorting($sort_by, $sens);
		
		return array(
			'data'         => $this->model->getAllNews(($page-1)*$n, $n, $sort[0], $sort[1] == 'ASC', array('published' => -1, 'publish_date' => -1)),
			'total'        => $this->model->countNews(),
			'current_page' => $page,
			'per_page'     => $n,
			'sorting_vars' => $sort,
			'sorting_tpl'  => $sortingHelper->getTplVars()
		);
	}
	
	/**
	 * Form to add/edit a news.
	 * 
	 * @param int $id_news
	 * @param array $db_data
	 */
	private function newsForm($id_news = 0, $db_data = array()) {
		if (WRequest::getMethod() == 'POST') {
			$errors = array();
			$post_data = WRequest::getAssoc(array('cats'));
			$data_translatable = array();
			
			// Format translatable fields
			$translatable_fields = array('title', 'content', 'author', 'url', 'meta_title', 'meta_description', 'published', 'publish_date', 'publish_time');
			$lang_list = WLang::getLangIDS(true);
			foreach ($translatable_fields as $field) {
				foreach ($lang_list as $i => $id_lang) {
					$value = WRequest::get($field.'_'.$id_lang);

					if ($field == 'published') {
						$value = ($value == 'on') ? 1 : 0;
					} else if ($field == 'url') {
						$value = strtolower(WTools::stripAccents($value));
						$value = preg_replace('#[^a-zA-Z0-9\/\._-]+#', '-', $value);
						$value = trim($value, '-');
					}
					
					if (($value === null || $value === '') && $i > 0) {
						// Use the value of the default lang
						$data_translatable[$id_lang][$field] = $data_translatable[$lang_list[0]][$field];
					} else {
						$data_translatable[$id_lang][$field] = $value;
					}
				}
			}
			
			/* BEGING VARIABLES CHECKING */
			if (empty($data_translatable[$lang_list[0]]['title'])) {
				$errors[] = WLang::get('news_no_title');
			}
			
			if (empty($data_translatable[$lang_list[0]]['author'])) {
				$errors[] = WLang::get('news_no_author');
			}
			
			foreach ($data_translatable as $id_lang => $fields) {
				if (!$data_translatable[$id_lang]['published']) {
					$data_translatable[$id_lang]['publish_date'] = '';
				} else if (empty($data_translatable[$id_lang]['publish_date']) || empty($data_translatable[$id_lang]['publish_time'])) {
					var_dump($data_translatable[$id_lang]);
					$errors[] = WLang::get('news_no_publish_date');
				} else {
					$data_translatable[$id_lang]['publish_date'] .= ' '.$data_translatable[$id_lang]['publish_time'];
				}

				unset($data_translatable[$id_lang]['publish_time']);
			}
			/* END VARIABLES CHECKING */
			
			// Image
			if (!empty($_FILES['image']['name'])) {
				$this->makeUploadDir();

				$upload = WHelper::load('upload', array($_FILES['image']));
				$upload->allowed = array('image/*');

				$upload->Process($this->upload_dir);

				if (!$upload->processed) {
					$errors[] = $upload->error;
					$post_data['image'] = $db_data['image'];
				} else {
					$post_data['image'] = '/upload/news/'.$upload->file_dst_name;
					
					// Erase the previous image
					if (!empty($db_data['image'])) {
						@unlink($this->upload_dir.basename($db_data['image']));
					}
				}
			} else if (!empty($id_news)) {
				$post_data['image'] = $db_data['image'];
			} else {
				$post_data['image'] = '';
			}
			
			if (empty($errors)) {
				if (empty($id_news)) { // Add case
					if ($id_news = $this->model->createNews($post_data, $data_translatable)) {
						// Treat categories
						if (!empty($post_data['cats'])) {
							foreach ($post_data['cats'] as $id_cat => $v) {
								$this->model->addCatToNews($id_news, intval($id_cat));
							}
						}
						
						$this->setHeader('Location', Wroute::getDir().'admin/news');
						WNote::success('news_added', WLang::get('news_added', $data_translatable[$lang_list[0]]['title']));
					} else {
						WNote::error('news_not_added', WLang::get('news_not_added'));
					}
				} else { // Edit case
					if ($this->model->updateNews($id_news, $post_data, $data_translatable)) {
						// Treat categories
						$this->model->removeCatsFromNews($id_news);

						if (!empty($post_data['cats'])) {
							foreach ($post_data['cats'] as $id_cat => $v) {
								$this->model->addCatToNews($id_news, intval($id_cat));
							}
						}
						
						$this->setHeader('Location', Wroute::getDir().'admin/news');
						WNote::success('news_edited', WLang::get('news_edited', $data_translatable[$lang_list[0]]['title']));
					} else {
						WNote::error('news_not_edited', WLang::get('news_not_edited'));
					}
				}
			} else {
				WNote::error('data_errors', implode("<br />\n", $errors));
				$db_data = $post_data;
			}
		}
		
		return array(
			'data' => $db_data,
			'cats' => $this->model->getCatsStructure('name', 'ASC')
		);
	}
	
	protected function newsAdd($params) {
		return $this->newsForm();
	}
	
	/**
	 * Handles news-edit action
	 */
	protected function newsEdit($params) {
		$id_news = intval(array_shift($params));
		
		$db_data = $this->model->getNews($id_news);
		
		if ($db_data !== false) {
			return $this->newsForm($id_news, $db_data);
		} else {
			$this->setHeader('Location', WRoute::getDir().'admin/news');
			return WNote::error('news_not_found', WLang::get('news_not_found', $id_news));
		}
	}

	protected function newsSavePreview($params) {
		$data = WRequest::getAssoc(array('title', 'content'), '', 'POST');

		$data['author'] = trim($_SESSION['firstname'].' '.$_SESSION['lastname']);
		$data['created_date'] = date(WLang::get('wdate_format'), time());

		$_SESSION['news_preview'] = $data;

		return 'ok';
	}
	
	/**
	 * Handles news-delete action
	 * 
	 * @todo Handle properly the news_not_found case with Bootstrap
	 */
	protected function newsDelete($params) {
		$id_news = intval(array_shift($params));
		
		$db_data = $this->model->getNews($id_news);
		
		if ($db_data !== false) {
			if (in_array('confirm', $params)) {
				if (!empty($db_data['image'])) {
					@unlink($this->upload_dir.$data['image']);
				}
				
				$this->model->removeCatsFromNews($id_news);
				$this->model->deleteNews($id_news);
				
				$this->setHeader('Location', WRoute::getDir().'admin/news');
				WNote::success('news_deleted', WLang::get('news_deleted', $db_data['title_1']));
			}
			
			return $db_data;
		} else {
			$this->setHeader('Location', WRoute::getDir().'admin/news');
			return WNote::error('news_not_found', WLang::get('news_not_found', $id_news));
		}
	}
	
	/**
	 * Handles categories action
	 */
	protected function categories($params) {
		$post_data = WRequest::getAssoc(array('name'), null, 'POST');
		
		// Data was sent by form
		if (!in_array(null, $post_data, true)) {
			$post_data += WRequest::getAssoc(array('id', 'shortname', 'parent'));
			$errors = array();
			
			$id_cat = intval($post_data['id']);
			
			if (empty($post_data['name'])) {
				$errors[] = WLang::get('category_no_name');
			}
			
			// Format short name
			if (empty($post_data['shortname'])) {
				$post_data['shortname'] = $post_data['name'];
			}

			$post_data['shortname'] = strtolower(WTools::stripAccents($post_data['shortname']));
			$post_data['shortname'] = preg_replace('#[^a-zA-Z0-9\/\._-]+#', '-', $post_data['shortname']);
			$post_data['shortname'] = trim($post_data['shortname'], '-');
			
			if (empty($errors)) {
				if (empty($id_cat)) { // Add case
					if ($this->model->createCat($post_data)) {
						$this->setHeader('Location', WRoute::getDir().'admin/news/categories');
						WNote::success('category_added', WLang::get('category_added', $post_data['name']));
					} else {
						WNote::error('category_not_added', WLang::get('category_not_added'));
					}
				} else { // Edit case
					$db_data = $this->model->getCat($id_cat);
					
					// Check existing category
					if ($db_data !== false) {
						if ($this->model->updateCat($id_cat, $post_data)) {
							$this->setHeader('Location', WRoute::getDir().'admin/news/categories');
							WNote::success('category_edited', WLang::get('category_edited', $post_data['name']));
						} else {
							WNote::error('category_not_edited', WLang::get('category_not_edited'));
						}
					} else {
						WNote::error('category_not_found', WLang::get('category_not_found'));
					}
				}
			} else {
				WNote::error('data_errors', implode("<br />\n", $errors));
			}
		}
		
		// Sorting criteria given in URL
		$sort_by = '';
		$sens = '';
		if (!empty($params[0])) {
			sscanf(str_replace('-', ' ', $params[0]), '%s %s', $sort_by_crit, $sens);
			
			if (isset($this->model->cats_data_model['toDB'][$sort_by_crit])) {
				$sort_by = $sort_by_crit;
			}
		}
		
		// SortingHelper Helper
		$sortingHelper = WHelper::load('SortingHelper', array(
			array('name', 'shortname'), 
			'name', 'ASC'
		));
		$sort = $sortingHelper->findSorting($sort_by, $sens);
		
		return array(
			'data'        => $this->model->getCatsStructure($sort[0], $sort[1]),
			'post_data'   => $post_data,
			'sorting_tpl' => $sortingHelper->getTplVars()
		);
	}
	
	/**
	 * Handles category-delete
	 */
	protected function categoryDelete($params) {
		$id_cat = intval(array_shift($params));
		
		$db_data = $this->model->getCat($id_cat);
		
		if ($db_data !== false) {
			if (in_array('confirm', $params)) {
				$this->model->removeCatsFromNews($id_cat);
				$this->model->unlinkChildrenOfParentCat($id_cat);
				$this->model->deleteCat($id_cat);
				
				$this->setHeader('Location', WRoute::getDir().'admin/news/categories');
				WNote::success('category_deleted', WLang::get('category_deleted'));
			}
			
			return $db_data;
		} else {
			$this->setHeader('Location', WRoute::getDir().'admin/news/categories');
			return WNote::error('category_not_found', WLang::get('category_not_found'));
		}
	}

	private function makeUploadDir() {
		if (!is_dir($this->upload_dir)) {
			mkdir($this->upload_dir, 0777, true);
		}
	}
}

?>

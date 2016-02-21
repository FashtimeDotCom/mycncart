<?php
class ControllerPressPress extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('press/press');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);
		
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_press'),
			'href' => $this->url->link('press/all')
		);


		if (isset($this->request->get['press_id'])) {
			$press_id = (int)$this->request->get['press_id'];
		} else {
			$press_id = 0;
		}

		$this->load->model('press/press');
		$this->load->model('tool/image');
		$this->load->model('catalog/product');

		$press_info = $this->model_press_press->getPress($press_id);

		if ($press_info) {
			$url = '';

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . $this->request->get['tag'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['breadcrumbs'][] = array(
				'text' => $press_info['title'],
				'href' => $this->url->link('press/press', $url . '&press_id=' . $this->request->get['press_id'])
			);

			$this->document->setTitle($press_info['meta_title']);
			$this->document->setDescription($press_info['meta_description']);
			$this->document->setKeywords($press_info['meta_keyword']);
			$this->document->addLink($this->url->link('press/press', 'press_id=' . $this->request->get['press_id']), 'canonical');
			$data['heading_title'] = $press_info['title'];
			
			$data['text_empty'] = $this->language->get('text_empty');
			$data['text_press'] = $this->language->get('text_press');
			$data['text_created_date'] = $this->language->get('text_created_date');
			$data['text_loading'] = $this->language->get('text_loading');
			
			$data['button_continue'] = $this->language->get('button_continue');
			

			$data['title']        		= html_entity_decode($press_info['title'], ENT_QUOTES, 'UTF-8');
			$data['status']  	   		= $press_info['status'];
			$data['sort_order']   		= $press_info['sort_order'];
			$data['date_added']   		= $press_info['date_added'];
			$data['description'] 		= html_entity_decode($press_info['description'], ENT_QUOTES, 'UTF-8');
			
			$data['products'] = array();

			$results = $this->model_press_press->getPressProductRelated($this->request->get['press_id']);

			foreach ($results as $result) {
				
				$product_info = $this->model_catalog_product->getProduct($result['related_id']);
				
				if ($product_info['image']) {
					$image = $this->model_tool_image->resize($product_info['image'], $this->config->get('config_image_related_width'), $this->config->get('config_image_related_height'));
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('config_image_related_width'), $this->config->get('config_image_related_height'));
				}

				if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')));
				} else {
					$price = false;
				}

				if ((float)$product_info['special']) {
					$special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')));
				} else {
					$special = false;
				}

				if ($this->config->get('config_tax')) {
					$tax = $this->currency->format((float)$product_info['special'] ? $product_info['special'] : $product_info['price']);
				} else {
					$tax = false;
				}

				if ($this->config->get('config_review_status')) {
					$rating = (int)$product_info['rating'];
				} else {
					$rating = false;
				}

				$data['products'][] = array(
					'product_id'  => $product_info['product_id'],
					'thumb'       => $image,
					'name'        => $product_info['name'],
					'description' => utf8_substr(strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8')), 0, $this->config->get('config_product_description_length')) . '..',
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'minimum'     => $product_info['minimum'] > 0 ? $product_info['minimum'] : 1,
					'rating'      => $rating,
					'href'        => $this->url->link('product/product', 'product_id=' . $product_info['product_id'])
				);
			}

			// Press Category Menu
			$this->load->model('press/category');
	
			$data['categories'] = array();
	
			$categories = $this->model_press_category->getPressCategories(0);
	
			foreach ($categories as $category) {
	
				// Level 2
				$children_data = array();
		
				$children = $this->model_press_category->getPressCategories($category['press_category_id']);
		
				foreach ($children as $child) {
		
					$children_data[] = array(
						'name'  => $child['name'],
						'href'  => $this->url->link('press/category', 'road=' . $category['press_category_id'] . '_' . $child['press_category_id'])
					);
				}
		
				// Level 1
				$data['categories'][] = array(
					'name'     => $category['name'],
					'children' => $children_data,
					'href'     => $this->url->link('press/category', 'road=' . $category['press_category_id'])
				);
				
			}

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/press/press.tpl')) {
				$this->response->setOutput($this->load->view($this->config->get('config_template') . '/template/press/press.tpl', $data));
			} else {
				$this->response->setOutput($this->load->view('default/template/press/press.tpl', $data));
			}
		} else {
			$url = '';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('press/press', $url . '&press_id=' . $press_id)
			);

			$this->document->setTitle($this->language->get('text_error'));

			$data['heading_title'] = $this->language->get('text_error');

			$data['text_error'] = $this->language->get('text_error');

			$data['button_continue'] = $this->language->get('button_continue');

			$data['continue'] = $this->url->link('press/all');

			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/error/not_found.tpl')) {
				$this->response->setOutput($this->load->view($this->config->get('config_template') . '/template/error/not_found.tpl', $data));
			} else {
				$this->response->setOutput($this->load->view('default/template/error/not_found.tpl', $data));
			}
		}
	}

}
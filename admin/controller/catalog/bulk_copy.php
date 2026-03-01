<?php
class ControllerCatalogBulkCopy extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('catalog/bulk_copy');

        $this->document->addStyle('view/stylesheet/bulk_copy.css');

        $data['user_token'] = $this->session->data['user_token'];

        // Load other language strings and data as needed
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        // Load view template
        return $this->load->view('catalog/bulk_copy', $data);
    }

    public function getProductInfo()
    {
        $this->load->language('catalog/bulk_copy');
        $json = [];

        if (!$this->user->hasPermission('access', 'catalog/bulk_copy')) {
            $json['error'] = $this->language->get('error_permission');
        } elseif (!isset($this->request->get['product_id'])) {
            $json['error'] = 'Missing product_id';
        } else {
            $product_id = (int)$this->request->get['product_id'];

            $this->load->model('catalog/product');

            $product = $this->model_catalog_product->getProduct($product_id);
            if (!$product) {
                $json['error'] = 'Product not found';
            } else {
                // Get product description
                $product_description = $this->model_catalog_product->getProductDescriptions($product_id);
                $name = isset($product_description[$this->config->get('config_language_id')]['name'])
                    ? $product_description[$this->config->get('config_language_id')]['name']
                    : $product['name'];

                $json['product'] = [
                    'product_id' => $product_id,
                    'name' => $name,
                ];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function getAttributes()
    {
        $this->load->language('catalog/bulk_copy');
        $json = [];

        if (!$this->user->hasPermission('access', 'catalog/bulk_copy')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            // Get all attributes
            $this->load->model('catalog/attribute');
            $attributes_raw = $this->model_catalog_attribute->getAttributes();
            $attributes_select = [];

            foreach ($attributes_raw as $attribute) {
                $attribute_info = $this->model_catalog_attribute->getAttribute($attribute['attribute_id']);

                if ($attribute_info && $attribute_info['type'] == 'select') {
                    $attributes_select_values = $this->model_catalog_attribute->getAttributeValueDescriptions($attribute['attribute_id']);

                    $attributes_select[] = array(
                        'attribute_id'      => $attribute['attribute_id'],
                        'name'              => $attribute_info['name'],
                        'type'              => $attribute_info['type'],
                        'attribute_values'  => $attributes_select_values
                    );
                }
            }
            $json['attributes'] = $attributes_select;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function getAttributeValues()
    {
        $this->load->language('catalog/bulk_copy');
        $json = [];

        if (!$this->user->hasPermission('access', 'catalog/bulk_copy')) {
            $json['error'] = $this->language->get('error_permission');
        } elseif (!isset($this->request->get['attribute_id'])) {
            $json['error'] = 'Missing attribute_id';
        } else {
            $attribute_id = (int)$this->request->get['attribute_id'];

            $this->load->model('catalog/attribute');
            $attribute_values = $this->model_catalog_attribute->getAttributeValueDescriptions($attribute_id);

            if (!$attribute_values) {
                $json['error'] = 'No attribute values found';
            } else {
                foreach ($attribute_values as $value) {
                    $json['attribute_values'][] = [
                        'value_id' => $value['attribute_value_id'],
                        'name'     => $value['attribute_value_description'][$this->config->get('config_language_id')]['name']
                    ];
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function bulkCopyProduct()
    {
        $this->load->language('catalog/bulk_copy');
        $json = [];

        // Check permissions
        if (!$this->user->hasPermission('modify', 'catalog/bulk_copy')) {
            $json['error'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Get attribute ID (selected in the modal)
        $attribute_id = isset($this->request->post['bulk_copy_attribute'])
            ? (int)$this->request->post['bulk_copy_attribute']
            : 0;

        if (!$attribute_id) {
            $json['error'] = $this->language->get('error_missing_attribute_id');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Get product IDs (array of selected products)
        $product_ids = isset($this->request->post['product_id'])
            ? (array)$this->request->post['product_id']
            : [];

        if (empty($product_ids)) {
            $json['error'] = $this->language->get('error_missing_product_id');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Get attribute values per product:
        // product_attribute_values[product_id] = [value_id1, value_id2, ...]
        $product_attribute_values = isset($this->request->post['product_attribute_values'])
            ? $this->request->post['product_attribute_values']
            : [];

        // Validate that we have at least one attribute value overall
        $total_values = 0;
        foreach ($product_ids as $pid) {
            if (!empty($product_attribute_values[$pid]) && is_array($product_attribute_values[$pid])) {
                $total_values += count($product_attribute_values[$pid]);
            }
        }

        if ($total_values === 0) {
            $json['error'] = $this->language->get('error_no_attribute_values');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Additional options
        $products_status = isset($this->request->post['products_status'])
            ? (int)$this->request->post['products_status']
            : 0;

        $products_title = isset($this->request->post['products_title'])
            ? (int)$this->request->post['products_title']
            : 0;

        $bulk_add_type = isset($this->request->post['bulk_add_type'])
            ? (int)$this->request->post['bulk_add_type']
            : 0;

        // Debug payload (optional, can be removed in production)
        $json['data'] = [
            'product_ids'            => $product_ids,
            'attribute_id'           => $attribute_id,
            'product_attribute_values' => $product_attribute_values,
            'products_status'        => $products_status,
            'products_title'         => $products_title,
            'bulk_add_type'          => $bulk_add_type
        ];

        $this->load->model('catalog/bulk_copy');
        $this->load->model('catalog/product');

        $new_products = [];

        // Loop over each selected product and its attribute values
        foreach ($product_ids as $product_id) {
            // Skip products without selected attribute values
            if (empty($product_attribute_values[$product_id]) || !is_array($product_attribute_values[$product_id])) {
                continue;
            }

            foreach ($product_attribute_values[$product_id] as $value_id) {
                $value_id = (int)$value_id;
                if (!$value_id) {
                    continue;
                }

                // copyProduct should handle cloning base product with new attribute value
                $copy_product_result = $this->model_catalog_bulk_copy->copyProduct(
                    (int)$product_id,
                    $attribute_id,
                    $value_id,
                    $products_status,
                    $products_title
                );

                if ($copy_product_result) {
                    $new_products[] = $copy_product_result;
                }
            }
        }

        if (empty($new_products)) {
            $json['error'] = $this->language->get('error_fetching_data');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // bulk_add_type === 0: delete original products after copy
        if ($bulk_add_type === 0) {
            foreach ($product_ids as $product_id) {
                $this->model_catalog_product->deleteProduct((int)$product_id);
            }
        }

        // Success message with count of new products
        $json['success'] = sprintf($this->language->get('text_success'), count($new_products));

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }


    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'catalog/bulk_copy')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        // Additional validation logic
        // ...

        return !$this->error;
    }
}

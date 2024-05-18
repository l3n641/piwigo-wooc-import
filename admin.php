<?php

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

global $template;

if (empty($_FILES["file"])) {
    $query = 'SELECT * FROM ' . SITES_TABLE;

    $result = pwg_query($query);

    while ($row = pwg_db_fetch_assoc($result)) {
        $is_remote = url_is_remote($row['galleries_url']);

        $tpl_var =
            array(
                'id' => $row['id'],
                'name' => $row['galleries_url'],
                'type' => l10n($is_remote ? 'Remote' : 'Local'),
            );

        $template->append('sites', $tpl_var);
    }

    $template->set_filename('plugin_admin_content', dirname(__FILE__) . '/tpl/admin.tpl');
    $template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
    return;
}

$file = $_FILES['file']['tmp_name'];
$site_id = intval($_POST["site_id"]);

$query = sprintf("select * from %s where id=%d limit 1", SITES_TABLE, $site_id);
$result = pwg_db_fetch_assoc(pwg_query($query));
if (!$file || !$site_id) {
    return;
}
$import = new Import($file, $site_id);
$result = $import->update();
$template->assign('result', $result);
$template->set_filename('plugin_admin_content', dirname(__FILE__) . '/tpl/admin.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');


class Product
{
    private $meta;

    public function __construct($meta)
    {
        $this->meta = $meta;
    }

    private function get_meta_data($index)
    {
        if (!empty($this->meta[$index])) {
            return $this->meta[$index];
        }
        return "";
    }

    public function get_product_gallery(): array
    {
        $images = $this->get_meta_data(27);
        if ($images) {
            return explode(" ", $images);
        }
        return [];
    }

    public function get_image_first()
    {
        return $this->get_meta_data(8);
    }

    public function get_image_sku()
    {
        $image = $this->get_image_first();
        if ($image) {
            $data = explode("/", $image);
            if (count($data) == 3) {
                return $data[1];
            }
        }
        return null;
    }

    public function get_post_title()
    {
        return $this->get_meta_data(1);
    }

    public function get_post_content()
    {
        return $this->get_meta_data(2);
    }

    public function get_post_except()
    {
        return $this->get_meta_data(3);
    }

    public function get_sale_price()
    {
        return $this->get_meta_data(5);
    }

    public function get_tags(): array
    {
        $tags = $this->get_meta_data(7);
        if ($tags) {
            return explode("|", $tags);
        }
        return [];
    }

    public function get_sizes(): array
    {
        $data = $this->get_meta_data(9);
        if ($data) {
            $data = str_replace("drop_down->", "", $data);
            return array_slice(explode("|", $data), 1);
        }
        return [];
    }


}


class Import
{

    private $csv_file;
    private $site_id;

    public function __construct($csv_file, $site_id)
    {
        $this->csv_file = $csv_file;
        $this->site_id = $site_id;
    }


    public function read_csv(): array
    {
        $products = [];

        if (($handle = fopen($this->csv_file, 'r')) !== false) {
            fgetcsv($handle);
            // 读取 CSV 文件的每一行，直到文件结束
            while (($data = fgetcsv($handle)) !== false) {
                $products[] = new Product($data);
            }
            // 关闭文件句柄
            fclose($handle);
        }
        return $products;
    }


    public function update(): array
    {
        $products = $this->read_csv();
        $success_quantity = 0;
        $failed_sku = [];
        foreach ($products as $product) {
            $sku = $product->get_image_sku();
            if (!$sku) {
                continue;
            }
            $category = $this->get_category($sku);
            if (!$category) {
                $failed_sku[] = $sku;
                continue;
            }

            $this->update_category($category["id"], $product->get_post_except());

            $title = $this->get_max_len_word($product->get_post_title());
            $name = $title . " ($ " . $product->get_sale_price() . " )";
            $comment = "";
            if ($product->get_sizes()) {
                $size = "<ul class='size-chart'>";
                foreach ($product->get_sizes() as $item) {
                    $size .= "<li>$item</li>";
                }
                $size .= "</ul>";
                $comment = "<h2>Size Chart</h2>" . $size;
            }
            $this->update_images($category["id"], $name, $comment);

            $image_ids = $this->get_image_ids($category["id"]);
            if ($product->get_tags()) {
                foreach ($image_ids as $id) {
                    $this->save_image_tags($id, $product->get_tags());
                }
            }
            $success_quantity = $success_quantity + 1;

        }
        return ["total" => count($products), "success_quantity" => $success_quantity, "failed_sku" => $failed_sku];
    }

    private function get_max_len_word($text, $maxLength = 245)
    {
        // 检查字符串长度是否超过最大长度
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        // 截取前 $maxLength 个字符
        $truncatedText = substr($text, 0, $maxLength);

        // 查找最后一个空格的位置，确保不在单词中间截断
        $lastSpacePosition = strrpos($truncatedText, ' ');

        if ($lastSpacePosition !== false) {
            // 截断到最后一个空格的位置
            return substr($truncatedText, 0, $lastSpacePosition);
        } else {
            // 如果没有找到空格，直接返回截断的文本
            return $truncatedText;
        }
    }


    public function get_category($dir)
    {
        $query = sprintf("select * from %s  where site_id='%d' and dir='%s' ", CATEGORIES_TABLE, $this->site_id, $dir);

        return pwg_db_fetch_assoc(pwg_query($query));

    }

    public function update_category($category_id, $post_content)
    {
        $post_content = pwg_db_real_escape_string($post_content);
        $query = sprintf("update  %s set comment ='%s' where id='%d' ", CATEGORIES_TABLE, $post_content, $category_id);
        return pwg_query($query);
    }

    public function update_images($category_id, $name, $comment)
    {
        $comment = pwg_db_real_escape_string($comment);
        $query = sprintf("update  %s set name ='%s',comment ='%s' where storage_category_id='%d' ", IMAGES_TABLE, $name, $comment, $category_id);
        return pwg_query($query);
    }

    public function get_tag_id($name)
    {
        $query = sprintf("select * from %s where name='%s' limit 1", TAGS_TABLE, $name);
        $result = pwg_db_fetch_assoc(pwg_query($query));
        if ($result) {
            return $result["id"];
        }
        $create_sql = "insert into %s (name,url_name) values('%s','%s')";
        $query = sprintf($create_sql, TAGS_TABLE, $name, $name);
        pwg_query($query);
        return pwg_db_insert_id();
    }

    public function get_image_ids($category_id)
    {
        $sql = "select id from %s where storage_category_id='%d' ";
        $query = sprintf($sql, IMAGES_TABLE, $category_id);
        return pwg_db_fetch_assoc(pwg_query($query));
    }

    public function save_image_tags($image_id, $tags): void
    {
        $sql = "delete from %s where image_id=%d";
        $query = sprintf($sql, IMAGE_TAG_TABLE, $image_id);
        pwg_query($query);

        foreach ($tags as $tag) {
            $tag_id = $this->get_tag_id($tag);
            $create_sql = 'insert into %s (image_id,tag_id) values(%s,%s)';
            $query = sprintf($create_sql, IMAGE_TAG_TABLE, $image_id, $tag_id);
            pwg_query($query);
        }
    }


}

?>
<?php
/**
 * Plugin Name: CVIP PRINT PDF
 * Description: A plugin to generate a PDF of a product image, gallery and description.
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function cvipppdf_add_pdf_link() {
    if ( is_product() ) {

        if (isset($_GET['generate-pdf'])) {
            global $product;
            $featuredURL = get_the_post_thumbnail_url();

            $attachment_ids = $product->get_gallery_image_ids();
            //Guardar la descripcion del producto
            $descripcion = $product->get_description();
            //escapa la descipcion
            $descripcion = wp_strip_all_tags(json_encode($descripcion));
            $anchoFeatured = 100;
            $anchoGallery = 50;
            $posGalleryX = 5;
            $posGalleryY = 5+$anchoFeatured;
            $image_urls = [];
            foreach( $attachment_ids as $attachment_id ) {
                $image_urls[] = wp_get_attachment_url( $attachment_id );
            }
            echo "Generando PDF";        
            ?>
            <script src="<?php echo plugin_dir_url( __FILE__ )?>/js/node_modules/jspdf/dist/jspdf.umd.min.js"></script>
            <script>
                const { jsPDF } = window.jspdf;

                const doc = new jsPDF();

                //FEATURED
                doc.addImage(
                    '<?=$featuredURL?>', 
                    'JPEG', 
                    5, 
                    5, 
                    <?=$anchoFeatured?>, 
                    <?=$anchoFeatured?>
                );
                //GALLERY
                <?php 
                $count = 1;
                foreach($image_urls as $imageURL) { ?>
                    doc.addImage(
                        '<?=$imageURL?>', 
                        'JPEG', 
                        <?=$posGalleryX?>,
                        <?=$posGalleryY?>,
                        <?=$anchoGallery?>,
                        <?=$anchoGallery?>
                    );
                    <?php 
                    $posGalleryX=($posGalleryX==5)?55:5; 
                    if ($count == 2) { 
                        $posGalleryY+=$anchoGallery;
                        $count = 1; 
                    }
                    else {
                        $count++;
                    }
                }  ?>
                doc.text('<?=$descripcion ?>', 110, 5);
                doc.save('output.pdf');
                


            </script>
            <?php
        }
        else {
            echo '<a href="?generate-pdf" id="generate-pdf-link">Generate PDF</a>';
        }
        
    }
}
add_action( 'woocommerce_before_single_product', 'cvipppdf_add_pdf_link' );

?>
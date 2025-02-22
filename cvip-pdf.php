<?php
/**
 * Plugin Name: CVIP PDF
 * Description: A plugin to generate a PDF of a product image, gallery and description, the link is generated via shortcode, example usage: <code><strong>[cvippdf linkText="Genera PDF de este producto" generandoText="Generando PDF (Por favor acepta la descarga)"  textoPie="www.YourSite.com | phone: 506 8888-8888 | Columbia Central city"]</strong></code>.
 * Version: 1.5.2
 * Author: Colombiavip.com
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class CVIP_PDF{
    //Textos por defecto
    public $atts = [
        "linktext" => "Genera PDF de este producto",
        "generandotext" => "Generando PDF (Por favor acepta la descarga)",
        "textopie" => "www.YourSite.com | phone: 506 8888-8888 | Columbia Central city"
    ];
    public function __construct(){
        add_shortcode('cvippdf', array($this, 'cvippdf_shortcode'));
    }
    public function cvippdf_shortcode($atts) {
        $this->atts = array_merge($this->atts,$atts);
        ob_start();
        $this->cvippdf_add_pdf_link();
        return ob_get_clean();
    }
    public function cvippdf_add_pdf_link() {
        if ( is_product() ) {
    
            if (isset($_GET['generate-pdf'])) {
                echo $this->atts["generandotext"];
                add_action( 'wp_footer', array($this, 'cvippdf_script') );
            }
            else {
                echo "<a href='?generate-pdf' id='generate-pdf-link'>".$this->atts["linktext"]."</a>";
            }
            
        }
    }
    public function cvippdf_script() {
        global $product;
        $featuredURL = get_the_post_thumbnail_url();
    
        // Obtener el logo del sitio
        $logo = get_theme_mod( 'custom_logo' );
        $logo =  wp_get_attachment_url( $logo );
    
        // Texto del pie de página
        $textoPie= trim(
            json_encode(
                $this->atts["textopie"]
            ),'\'"'
        );
    
        //Guardar la descripcion del producto
        $descripcion = $product->get_description();
        //escapa la descipcion
        $descripcion = 
        substr(str_replace("'","",
            wp_strip_all_tags(
                json_encode($descripcion)
            )
        ), 0,2000);
        $anchoFeatured = 100;
        $anchoGallery = 50;
        $posGalleryX = 5;
        $posGalleryY = 5+$anchoFeatured;
        $image_urls = [];
    
    
    
        $attachment_ids = $product->get_gallery_image_ids();
        //Obtengo solo los primeros 6 elementos
        $attachment_ids = array_slice($attachment_ids, 0, 6);
    
        foreach( $attachment_ids as $attachment_id ) {
            $image_urls[] = wp_get_attachment_url( $attachment_id );
        }
    
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
            doc.setFontSize(10);
            doc.text(
                '<?=$descripcion ?>', 
                110, 
                10,
                { maxWidth: 90 }
            );
            doc.addImage(
                '<?=$logo?>', 
                'JPEG', 
                5, 
                265, 
                30, 
                30                    
            );
            doc.text(
                '<?=$textoPie ?>', 
                40, 
                280,
                { maxWidth: 280 }
            );
    
            doc.setPage(1);
            doc.saveGraphicsState();
            doc.setGState(new doc.GState({opacity: 0.1}));
            //LOGO
            doc.addImage(
                '<?=$logo?>', 
                'JPEG', 
                5, 
                5, 
                200, 
                200                    
            );
            // window.open(doc.output('bloburl'));
            doc.save('output.pdf');
            
    
    
        </script>
        <?php
    }
    
    

    
    

}

new CVIP_PDF();



?>

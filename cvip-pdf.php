<?php
/**
 * Plugin Name: CVIP Product PDF & CSV Catalog
 * Description: To generate a PDF of a product image, gallery and description, usage: <code><strong>[cvippdf linkText="Genera PDF de este producto" generandoText="Generando PDF (Por favor acepta la descarga)"  textoPie="www.YourSite.com | phone: 506 8888-8888 | Columbia Central city"]</strong></code>. <br>To add a catalog download button on any page, use:<code><strong>[boton_descargar_productos linktext="Descargar inventario"]</strong></code>
 * Version: 1.7.0
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
        add_shortcode('boton_descargar_productos', array($this, 'catalog_csv_shortcode'));
        add_action('wp_ajax_exportar_productos_csv', array($this, 'export_products_csv'));
        add_action('wp_ajax_nopriv_exportar_productos_csv', array($this, 'export_products_csv'));
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

    public function catalog_csv_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'linktext' => 'Descargar catálogo (CSV)',
            ),
            $atts,
            'boton_descargar_productos'
        );

        $url = admin_url( 'admin-ajax.php?action=exportar_productos_csv' );

        return sprintf(
            '<a href="%s" class="boton-descargar-csv">%s</a>',
            esc_url( $url ),
            esc_html( $atts['linktext'] )
        );
    }

    public function export_products_csv() {
        if ( ! function_exists( 'wc_get_products' ) ) {
            wp_die( 'WooCommerce is required.', '', array( 'response' => 503 ) );
        }

        $cache_key   = 'cvip_export_productos_csv_semicolon';
        $csv_cacheado = get_transient( $cache_key );

        if ( false === $csv_cacheado ) {
            $csv_cacheado = $this->build_products_csv();
            set_transient( $cache_key, $csv_cacheado, HOUR_IN_SECONDS );
        }

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=productos-' . gmdate( 'Y-m-d' ) . '.csv' );

        echo "\xEF\xBB\xBF";
        echo $csv_cacheado;
        exit;
    }

    private function build_products_csv() {
        $productos = wc_get_products(
            array(
                'status' => 'publish',
                'limit'  => -1,
            )
        );

        $output = fopen( 'php://temp', 'w+' );
        $meta_keys = array(
            'marca',
            'modelo',
            'version',
            'placa',
            'ciudad',
            'ano',
            'transmision',
            'cilindrada',
        );

        fputcsv(
            $output,
            array(
                'Fecha de publicación',
                'Nombre',
                'Precio',
                'Marca',
                'Modelo',
                'Versión',
                'Placa',
                'Ciudad',
                'Año',
                'Tipo de caja',
                'Cilindraje',
            ),
            ';'
        );

        foreach ( $productos as $producto ) {
            $fecha = $producto->get_date_created();
            $row   = array(
                $fecha ? $fecha->date_i18n( 'Y-m-d H:i:s' ) : '',
                $producto->get_name(),
                $producto->get_price(),
            );

            foreach ( $meta_keys as $meta_key ) {
                $row[] = $this->get_product_meta_value( $producto->get_id(), $meta_key );
            }

            fputcsv( $output, $row, ';' );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }

    private function get_product_meta_value( $product_id, $meta_key ) {
        $value = get_post_meta( $product_id, $meta_key, true );

        if ( is_array( $value ) ) {
            $value = implode( ', ', $value );
        }

        return wp_strip_all_tags( (string) $value );
    }

    private function get_image_meta( $url, $attachment_id = null ) {
        $mime = $attachment_id ? get_post_mime_type( $attachment_id ) : '';
        $is_svg = ( $mime === 'image/svg+xml' ) || (bool) preg_match( '/\.svg(\?|#|$)/i', (string) $url );

        $format = 'JPEG';
        if ( ! $is_svg && $mime ) {
            if ( strpos( $mime, 'png' ) !== false ) {
                $format = 'PNG';
            } elseif ( strpos( $mime, 'webp' ) !== false ) {
                $format = 'WEBP';
            } elseif ( strpos( $mime, 'gif' ) !== false ) {
                $format = 'GIF';
            }
        }

        return array(
            'url'    => $url ? esc_url_raw( $url ) : '',
            'is_svg' => $is_svg,
            'format' => $format,
        );
    }

    public function cvippdf_script() {
        global $product;
        $plugin_url = plugin_dir_url( __FILE__ );

        $featured_id = get_post_thumbnail_id();
        $featured_url = get_the_post_thumbnail_url();
        $featured = $this->get_image_meta( $featured_url, $featured_id );

        $logo_id = get_theme_mod( 'custom_logo' );
        $logo_url = wp_get_attachment_url( $logo_id );
        $logo = $this->get_image_meta( $logo_url, $logo_id );

        $descripcion = substr( wp_strip_all_tags( $product->get_description() ), 0, 2000 );

        $anchoFeatured = 100;
        $anchoGallery = 50;
        $posGalleryX = 5;
        $posGalleryY = 5 + $anchoFeatured;
        $gallery = array();

        $attachment_ids = $product->get_gallery_image_ids();
        $attachment_ids = array_slice( $attachment_ids, 0, 6 );

        $count = 1;
        foreach ( $attachment_ids as $attachment_id ) {
            $image_url = wp_get_attachment_url( $attachment_id );
            $gallery[] = array_merge(
                $this->get_image_meta( $image_url, $attachment_id ),
                array(
                    'x' => $posGalleryX,
                    'y' => $posGalleryY,
                    'w' => $anchoGallery,
                    'h' => $anchoGallery,
                )
            );

            $posGalleryX = ( $posGalleryX === 5 ) ? 55 : 5;
            if ( $count === 2 ) {
                $posGalleryY += $anchoGallery;
                $count = 1;
            } else {
                $count++;
            }
        }

        $pdf_data = array(
            'featured' => array_merge(
                $featured,
                array(
                    'x' => 5,
                    'y' => 5,
                    'w' => $anchoFeatured,
                    'h' => $anchoFeatured,
                )
            ),
            'gallery'       => $gallery,
            'logoFooter'    => array_merge(
                $logo,
                array(
                    'x' => 5,
                    'y' => 265,
                    'w' => 30,
                    'h' => 30,
                )
            ),
            'logoWatermark' => array_merge(
                $logo,
                array(
                    'x' => 5,
                    'y' => 5,
                    'w' => 200,
                    'h' => 200,
                )
            ),
            'description' => $descripcion,
            'footerText'  => $this->atts['textopie'],
        );

        ?>
        <script src="<?php echo esc_url( $plugin_url ); ?>build/jspdf.umd.min.js"></script>
        <script src="<?php echo esc_url( $plugin_url ); ?>build/svg2pdf.umd.min.js"></script>
        <script>
            (function() {
                const pdfData = <?php echo wp_json_encode( $pdf_data ); ?>;

                (async function() {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF();

                    async function addImageOrSvg(image) {
                        if (!image.url) {
                            return;
                        }

                        if (image.is_svg) {
                            const res = await fetch(image.url);
                            if (!res.ok) {
                                throw new Error('Failed to load SVG: ' + image.url);
                            }

                            const svgEl = new DOMParser()
                                .parseFromString(await res.text(), 'image/svg+xml')
                                .documentElement;

                            await doc.svg(svgEl, {
                                x: image.x,
                                y: image.y,
                                width: image.w,
                                height: image.h
                            });
                        } else {
                            doc.addImage(
                                image.url,
                                image.format,
                                image.x,
                                image.y,
                                image.w,
                                image.h
                            );
                        }
                    }

                    await addImageOrSvg(pdfData.featured);

                    for (const image of pdfData.gallery) {
                        await addImageOrSvg(image);
                    }

                    doc.setFontSize(10);
                    doc.text(pdfData.description, 110, 10, { maxWidth: 90 });

                    await addImageOrSvg(pdfData.logoFooter);

                    doc.text(pdfData.footerText, 40, 280, { maxWidth: 280 });

                    doc.setPage(1);
                    doc.saveGraphicsState();
                    doc.setGState(new doc.GState({ opacity: 0.1 }));
                    await addImageOrSvg(pdfData.logoWatermark);
                    doc.save('output.pdf');
                })().catch(function(err) {
                    console.error('CVIP PDF generation failed', err);
                });
            })();
        </script>
        <?php
    }
}

new CVIP_PDF();

?>

<?php
/**
 * Anexos e extracao de texto de documentos do fluxo complementar.
 *
 * Cluster extraido de EOP_Post_Confirmation_Flow sem alterar comportamento:
 * todos os metodos sao `private static` e nao tem chamador externo, entao
 * permanecem internos a classe que usa o trait. `self::` resolve para a
 * classe que faz o `use`, preservando as chamadas internas existentes.
 *
 * @package Aireset\ExpressoOrder
 */

defined( 'ABSPATH' ) || exit;

trait EOP_Attachment_Documents {

	private static function stream_attachment_file( $attachment_id, $force_download = false, $filename = '' ) {
		$file_path = get_attached_file( $attachment_id );
		$mime_type = (string) get_post_mime_type( $attachment_id );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'Arquivo nao encontrado.', EOP_TEXT_DOMAIN ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . ( $mime_type ? $mime_type : 'application/octet-stream' ) );
		header( 'Content-Disposition: ' . ( $force_download ? 'attachment' : 'inline' ) . '; filename="' . sanitize_file_name( $filename ? $filename : wp_basename( $file_path ) ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: private, max-age=0, must-revalidate' );
		header( 'Pragma: public' );

		readfile( $file_path );
		exit;
	}

	private static function get_attachment_mime_type( $attachment_id ) {
		$mime_type = (string) get_post_mime_type( $attachment_id );
		$file_path = get_attached_file( $attachment_id );

		if ( '' === $mime_type && ! empty( $file_path ) ) {
			$filetype = wp_check_filetype( $file_path );
			$mime_type = (string) ( $filetype['type'] ?? '' );
		}

		return $mime_type;
	}

	private static function extract_text_from_attachment( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		$mime_type = self::get_attachment_mime_type( $attachment_id );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return '';
		}

		if ( false !== strpos( $mime_type, 'wordprocessingml.document' ) || 'docx' === strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) ) {
			return self::extract_text_from_docx_file( $file_path );
		}

		if ( false !== strpos( $mime_type, 'msword' ) || 'doc' === strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) ) {
			return self::extract_text_from_doc_file( $file_path );
		}

		return '';
	}

	private static function extract_text_from_docx_file( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $file_path ) ) {
			return '';
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml || '' === $xml ) {
			return '';
		}

		$text = str_replace( array( '</w:p>', '</w:tr>', '</w:tc>' ), array( "\n\n", "\n", ' ' ), $xml );
		$text = preg_replace( '/<w:tab[^>]*\/>/i', "\t", (string) $text );
		$text = wp_strip_all_tags( $text );

		return self::normalize_extracted_document_text( $text );
	}

	private static function extract_text_from_doc_file( $file_path ) {
		$command_path = self::find_cli_binary( array( 'antiword' ) );

		if ( '' === $command_path ) {
			return '';
		}

		$output = array();
		$return = 0;
		exec( escapeshellarg( $command_path ) . ' ' . escapeshellarg( $file_path ) . ' 2>&1', $output, $return );

		if ( 0 !== $return || empty( $output ) ) {
			return '';
		}

		return self::normalize_extracted_document_text( implode( "\n", $output ) );
	}

	private static function find_cli_binary( $candidates ) {
		$paths = array_filter( explode( PATH_SEPARATOR, (string) getenv( 'PATH' ) ) );

		foreach ( (array) $candidates as $candidate ) {
			foreach ( $paths as $path ) {
				$path = rtrim( (string) $path, DIRECTORY_SEPARATOR );

				foreach ( array( $path . DIRECTORY_SEPARATOR . $candidate, $path . DIRECTORY_SEPARATOR . $candidate . '.exe' ) as $binary ) {
					if ( is_file( $binary ) && is_readable( $binary ) ) {
						return $binary;
					}
				}
			}
		}

		return '';
	}

	private static function normalize_extracted_document_text( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, get_bloginfo( 'charset' ) ? get_bloginfo( 'charset' ) : 'UTF-8' );
		$text = preg_replace( '/\r\n|\r/', "\n", $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/ ?\n ?/', "\n", $text );

		return trim( wp_strip_all_tags( (string) $text ) );
	}
}

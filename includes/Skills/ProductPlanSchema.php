<?php
declare(strict_types=1);
namespace SitePilot\Skills;

/**
 * Schema definition for AI-generated product plans.
 * Defines the structure, allowed values, and constraints
 * that the ProductPlanValidator enforces.
 */
final class ProductPlanSchema {

	/**
	 * Get the JSON schema for product creation/editing.
	 *
	 * @return array<string, mixed>
	 */
	public static function schema(): array {
		return array(
			'type'       => 'object',
			'required'   => array( 'name' ),
			'properties' => array(
				'name'              => array(
					'type'        => 'string',
					'description' => 'Product name / title',
					'maxLength'   => 255,
				),
				'description'       => array(
					'type'        => 'string',
					'description' => 'Full product description (can include HTML)',
				),
				'short_description' => array(
					'type'        => 'string',
					'description' => 'Short excerpt / description',
					'maxLength'   => 1024,
				),
				'price'             => array(
					'type'    => 'string',
					'pattern' => '^[0-9]+(\.[0-9]{1,2})?$',
				),
				'regular_price'     => array(
					'type'    => 'string',
					'pattern' => '^[0-9]+(\.[0-9]{1,2})?$',
				),
				'sale_price'        => array(
					'type'    => 'string',
					'pattern' => '^[0-9]+(\.[0-9]{1,2})?$',
				),
				'categories'        => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'tags'              => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'status'            => array(
					'type' => 'string',
					'enum' => array( 'draft', 'pending', 'publish' ),
				),
				'stock_status'      => array(
					'type' => 'string',
					'enum' => array( 'instock', 'outofstock', 'onbackorder' ),
				),
				'stock_quantity'    => array(
					'type'   => 'integer',
					'minimum'=> 0,
				),
				'manage_stock'       => array(
					'type' => 'boolean',
				),
				'type'              => array(
					'type' => 'string',
					'enum' => array( 'simple', 'variable', 'grouped', 'external' ),
				),
				'image_url'          => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'sku'               => array(
					'type'      => 'string',
					'maxLength' => 100,
				),
				'weight'            => array(
					'type' => 'string',
				),
				'dimensions'         => array(
					'type'     => 'object',
					'properties' => array(
						'length' => array( 'type' => 'string' ),
						'width'  => array( 'type' => 'string' ),
						'height' => array( 'type' => 'string' ),
					),
				),
			),
		);
	}

	/**
	 * Allowed product types.
	 */
	public const ALLOWED_TYPES = array( 'simple', 'variable', 'grouped', 'external' );

	/**
	 * Allowed product statuses.
	 */
	public const ALLOWED_STATUSES = array( 'draft', 'pending', 'publish' );

	/**
	 * Allowed stock statuses.
	 */
	public const ALLOWED_STOCK = array( 'instock', 'outofstock', 'onbackorder' );
}

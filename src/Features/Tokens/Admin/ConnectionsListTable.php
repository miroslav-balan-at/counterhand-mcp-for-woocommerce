<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Admin;

use Counterhand\Features\Tokens\Domain\ApiToken;
use Counterhand\Features\Tokens\Domain\ScopeSummary;
use Counterhand\Features\Tokens\Domain\TokenRepositoryInterface;
use Counterhand\Features\Tokens\Domain\TokenStatus;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists OAuth-granted connections (client, scopes, last-used) with a revoke
 * action. Read-only otherwise — no secret is ever shown.
 */
final class ConnectionsListTable extends \WP_List_Table {

	public function __construct( private readonly TokenRepositoryInterface $repository ) {
		parent::__construct(
			[
				'singular' => 'counterhand_connection',
				'plural'   => 'counterhand_connections',
				'ajax'     => false,
			]
		);
	}

	public function get_columns(): array {
		return [
			'client'       => __( 'Client', 'counterhand-mcp-for-woocommerce' ),
			'scopes'       => __( 'Granted scopes', 'counterhand-mcp-for-woocommerce' ),
			'status'       => __( 'Status', 'counterhand-mcp-for-woocommerce' ),
			'created_at'   => __( 'Connected', 'counterhand-mcp-for-woocommerce' ),
			'last_used_at' => __( 'Last used', 'counterhand-mcp-for-woocommerce' ),
			'actions'      => __( 'Actions', 'counterhand-mcp-for-woocommerce' ),
		];
	}

	public function prepare_items(): void {
		$this->_column_headers = [ $this->get_columns(), [], [] ];
		$this->items           = $this->repository->list_all();
	}

	public function no_items(): void {
		esc_html_e( 'No connections yet. Connect an AI assistant using the endpoint on the Connect tab.', 'counterhand-mcp-for-woocommerce' );
	}

	/** @param ApiToken $item */
	public function column_default( $item, $column_name ): string {
		return match ( $column_name ) {
			'client'       => $this->render_client( $item ),
			'scopes'       => $this->render_scope_badges( $item ),
			'status'       => $this->render_status( $item ),
			'created_at'   => esc_html( wp_date( get_option( 'date_format', 'Y-m-d' ), $item->created_at->getTimestamp() ) ),
			'last_used_at' => null !== $item->last_used_at
				? esc_html( human_time_diff( $item->last_used_at->getTimestamp() ) . ' ' . __( 'ago', 'counterhand-mcp-for-woocommerce' ) )
				: '<span class="counterhand-muted">' . esc_html__( 'never', 'counterhand-mcp-for-woocommerce' ) . '</span>',
			'actions'      => $this->render_actions( $item ),
			default        => '',
		};
	}

	private function render_client( ApiToken $item ): string {
		$name = $item->label;

		if ( null !== $item->client_id ) {
			return sprintf(
				'<strong>%s</strong><br><span class="counterhand-muted">%s</span>',
				esc_html( $name ),
				esc_html( $item->client_id )
			);
		}

		return esc_html( $name );
	}

	/** Beyond this the column stops informing and starts being a wall of badges. */
	private const BADGE_LIMIT = 3;

	private function render_scope_badges( ApiToken $item ): string {
		$summary = ScopeSummary::of( $item->scopes );
		$badges  = '';

		foreach ( $summary->shown( self::BADGE_LIMIT ) as $grant ) {
			$class   = $grant->writable ? 'counterhand-badge counterhand-badge--write' : 'counterhand-badge';
			$badges .= '<span class="' . esc_attr( $class ) . '">' . esc_html( $grant->badge() ) . '</span> ';
		}

		$hidden = $summary->hidden( self::BADGE_LIMIT );

		if ( 0 === $hidden ) {
			return $badges;
		}

		return $badges . '<span class="counterhand-muted">' . esc_html(
			sprintf(
				/* translators: %d: number of further tool groups this token can reach. */
				_n( '+%d more', '+%d more', $hidden, 'counterhand-mcp-for-woocommerce' ),
				$hidden
			)
		) . '</span>';
	}

	private function render_status( ApiToken $item ): string {
		$class = match ( $item->status ) {
			TokenStatus::Active  => 'counterhand-status counterhand-status--active',
			TokenStatus::Revoked => 'counterhand-status counterhand-status--revoked',
			TokenStatus::Expired => 'counterhand-status counterhand-status--expired',
		};

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $item->status->label() ) . '</span>';
	}

	private function render_actions( ApiToken $item ): string {
		if ( TokenStatus::Active !== $item->status ) {
			return '';
		}

		$form  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="counterhand-revoke-form">';
		$form .= '<input type="hidden" name="action" value="counterhand_revoke_connection">';
		$form .= '<input type="hidden" name="counterhand_token_id" value="' . esc_attr( (string) $item->id ) . '">';
		$form .= wp_nonce_field( 'counterhand_revoke_connection', '_wpnonce', true, false );
		$form .= '<button type="submit" class="button button-small">' . esc_html__( 'Revoke', 'counterhand-mcp-for-woocommerce' ) . '</button>';
		$form .= '</form>';

		return $form;
	}
}

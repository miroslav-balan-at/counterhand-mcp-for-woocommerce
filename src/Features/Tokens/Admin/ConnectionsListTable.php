<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Admin;

use AgentGateMcp\Features\Tokens\Domain\ApiToken;
use AgentGateMcp\Features\Tokens\Domain\ScopeSummary;
use AgentGateMcp\Features\Tokens\Domain\TokenRepositoryInterface;
use AgentGateMcp\Features\Tokens\Domain\TokenStatus;

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
				'singular' => 'agmcp_connection',
				'plural'   => 'agmcp_connections',
				'ajax'     => false,
			]
		);
	}

	public function get_columns(): array {
		return [
			'client'       => __( 'Client', 'agentgate-mcp-for-woocommerce' ),
			'scopes'       => __( 'Granted scopes', 'agentgate-mcp-for-woocommerce' ),
			'status'       => __( 'Status', 'agentgate-mcp-for-woocommerce' ),
			'created_at'   => __( 'Connected', 'agentgate-mcp-for-woocommerce' ),
			'last_used_at' => __( 'Last used', 'agentgate-mcp-for-woocommerce' ),
			'actions'      => __( 'Actions', 'agentgate-mcp-for-woocommerce' ),
		];
	}

	public function prepare_items(): void {
		$this->_column_headers = [ $this->get_columns(), [], [] ];
		$this->items           = $this->repository->list_all();
	}

	public function no_items(): void {
		esc_html_e( 'No connections yet. Connect an AI assistant using the endpoint on the Connect tab.', 'agentgate-mcp-for-woocommerce' );
	}

	/** @param ApiToken $item */
	public function column_default( $item, $column_name ): string {
		return match ( $column_name ) {
			'client'       => $this->render_client( $item ),
			'scopes'       => $this->render_scope_badges( $item ),
			'status'       => $this->render_status( $item ),
			'created_at'   => esc_html( wp_date( get_option( 'date_format', 'Y-m-d' ), $item->created_at->getTimestamp() ) ),
			'last_used_at' => null !== $item->last_used_at
				? esc_html( human_time_diff( $item->last_used_at->getTimestamp() ) . ' ' . __( 'ago', 'agentgate-mcp-for-woocommerce' ) )
				: '<span class="agmcp-muted">' . esc_html__( 'never', 'agentgate-mcp-for-woocommerce' ) . '</span>',
			'actions'      => $this->render_actions( $item ),
			default        => '',
		};
	}

	private function render_client( ApiToken $item ): string {
		$name = $item->label;

		if ( null !== $item->client_id ) {
			return sprintf(
				'<strong>%s</strong><br><span class="agmcp-muted">%s</span>',
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
			$class   = $grant->writable ? 'agmcp-badge agmcp-badge--write' : 'agmcp-badge';
			$badges .= '<span class="' . esc_attr( $class ) . '">' . esc_html( $grant->badge() ) . '</span> ';
		}

		$hidden = $summary->hidden( self::BADGE_LIMIT );

		if ( 0 === $hidden ) {
			return $badges;
		}

		return $badges . '<span class="agmcp-muted">' . esc_html(
			sprintf(
				/* translators: %d: number of further tool groups this token can reach. */
				_n( '+%d more', '+%d more', $hidden, 'agentgate-mcp-for-woocommerce' ),
				$hidden
			)
		) . '</span>';
	}

	private function render_status( ApiToken $item ): string {
		$class = match ( $item->status ) {
			TokenStatus::Active  => 'agmcp-status agmcp-status--active',
			TokenStatus::Revoked => 'agmcp-status agmcp-status--revoked',
			TokenStatus::Expired => 'agmcp-status agmcp-status--expired',
		};

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $item->status->value ) . '</span>';
	}

	private function render_actions( ApiToken $item ): string {
		if ( TokenStatus::Active !== $item->status ) {
			return '';
		}

		$form  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="agmcp-revoke-form">';
		$form .= '<input type="hidden" name="action" value="agmcp_revoke_connection">';
		$form .= '<input type="hidden" name="agmcp_token_id" value="' . esc_attr( (string) $item->id ) . '">';
		$form .= wp_nonce_field( 'agmcp_revoke_connection', '_wpnonce', true, false );
		$form .= '<button type="submit" class="button button-small">' . esc_html__( 'Revoke', 'agentgate-mcp-for-woocommerce' ) . '</button>';
		$form .= '</form>';

		return $form;
	}
}

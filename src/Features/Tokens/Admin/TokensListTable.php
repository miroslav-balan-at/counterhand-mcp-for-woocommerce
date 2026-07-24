<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Tokens\Admin;

use AgentGateMcp\Features\Tokens\Domain\ApiToken;
use AgentGateMcp\Features\Tokens\Domain\PlainToken;
use AgentGateMcp\Features\Tokens\Domain\TokenRepositoryInterface;
use AgentGateMcp\Features\Tokens\Domain\TokenStatus;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class TokensListTable extends \WP_List_Table {

	public function __construct( private readonly TokenRepositoryInterface $repository ) {
		parent::__construct(
			[
				'singular' => 'agmcp_token',
				'plural'   => 'agmcp_tokens',
				'ajax'     => false,
			]
		);
	}

	public function get_columns(): array {
		return [
			'label'        => __( 'Label', 'agentgate-mcp-for-woocommerce' ),
			'token'        => __( 'Token', 'agentgate-mcp-for-woocommerce' ),
			'scopes'       => __( 'Scopes', 'agentgate-mcp-for-woocommerce' ),
			'status'       => __( 'Status', 'agentgate-mcp-for-woocommerce' ),
			'created_at'   => __( 'Created', 'agentgate-mcp-for-woocommerce' ),
			'last_used_at' => __( 'Last used', 'agentgate-mcp-for-woocommerce' ),
			'actions'      => __( 'Actions', 'agentgate-mcp-for-woocommerce' ),
		];
	}

	public function prepare_items(): void {
		$this->_column_headers = [ $this->get_columns(), [], [] ];
		$this->items           = $this->repository->list_all();
	}

	/** @param ApiToken $item */
	public function column_default( $item, $column_name ): string {
		return match ( $column_name ) {
			'label'        => esc_html( $item->label ),
			'token'        => '<code>' . esc_html( PlainToken::PREFIX . '_' . substr( $item->token_id->value, 0, 4 ) . '…' . substr( $item->token_id->value, -4 ) ) . '</code>',
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

	private function render_scope_badges( ApiToken $item ): string {
		$badges = '';

		foreach ( $item->scopes->all() as $scope ) {
			$class   = $scope->is_write() ? 'agmcp-badge agmcp-badge--write' : 'agmcp-badge';
			$badges .= '<span class="' . esc_attr( $class ) . '">' . esc_html( $scope->value ) . '</span> ';
		}

		return $badges;
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
		$form .= '<input type="hidden" name="action" value="agmcp_revoke_token">';
		$form .= '<input type="hidden" name="agmcp_token_id" value="' . esc_attr( (string) $item->id ) . '">';
		$form .= wp_nonce_field( 'agmcp_revoke_token', '_wpnonce', true, false );
		$form .= '<button type="submit" class="button button-small agmcp-revoke-button">' . esc_html__( 'Revoke', 'agentgate-mcp-for-woocommerce' ) . '</button>';
		$form .= '</form>';

		return $form;
	}
}

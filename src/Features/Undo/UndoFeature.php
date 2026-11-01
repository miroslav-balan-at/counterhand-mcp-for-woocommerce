<?php

declare( strict_types=1 );

namespace Counterhand\Features\Undo;

use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\McpServer\ToolRegistrarInterface;
use Counterhand\Features\Undo\Persistence\ChangeSchema;
use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

final readonly class UndoFeature implements FeatureInterface {

	public const PURGE_HOOK = 'counterhand_purge_changes';

	public function __construct(
		private ToolRegistrarInterface $tool_registry,
		private ToolDispatcherInterface $tools,
		private WpdbChangeJournal $journal,
	) {}

	public function register(): void {
		foreach ( ChangeTool::both( $this->journal, $this->tools ) as $tool ) {
			$this->tool_registry->add( $tool );
		}

		add_action( 'admin_init', [ ChangeSchema::class, 'maybe_upgrade' ] );
		add_action( self::PURGE_HOOK, [ $this->journal, 'purge_expired' ] );

		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
	}
}

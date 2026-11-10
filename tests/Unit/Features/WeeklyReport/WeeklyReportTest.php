<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WeeklyReport;

use Brain\Monkey\Functions;
use Counterhand\Features\Playground\TextModelInterface;
use Counterhand\Features\McpServer\ToolDispatcherInterface;
use Counterhand\Features\WeeklyReport\FigureReader;
use Counterhand\Features\WeeklyReport\ReportSummary;
use Counterhand\Features\WeeklyReport\ReportWeek;
use Counterhand\Features\WeeklyReport\SalesFigures;
use Counterhand\Features\WeeklyReport\Subscriptions;
use Counterhand\Features\WeeklyReport\WeeklyFigures;
use Counterhand\Features\WeeklyReport\WeeklyReport;
use Counterhand\Tests\Unit\TestCase;

final class WeeklyReportTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_bloginfo' )->justReturn( 'Test store' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $text ): string => strip_tags( $text ) );
	}

	/** @return iterable<string, array{string, string, string}> */
	public static function moments(): iterable {
		yield 'Monday morning, just after the week ended' => [ '2026-11-09 07:00', '2026-11-02', '2026-11-08' ];
		yield 'Sunday night, the week not yet over'       => [ '2026-11-08 23:59', '2026-10-26', '2026-11-01' ];
		yield 'across the end of daylight saving'         => [ '2026-10-26 07:00', '2026-10-19', '2026-10-25' ];
	}

	/** @dataProvider moments */
	public function test_the_report_covers_the_last_complete_week( string $now, string $first, string $last ): void {
		$week = ReportWeek::last_full_week( new \DateTimeImmutable( $now, new \DateTimeZone( 'Europe/Vienna' ) ) );

		self::assertSame(
			[
				'date_min' => $first,
				'date_max' => $last,
			],
			$week->range()
		);
		self::assertSame( '2026-10-19', ReportWeek::last_full_week( new \DateTimeImmutable( '2026-11-02 07:00', new \DateTimeZone( 'Europe/Vienna' ) ) )->previous()->range()['date_min'] );
	}

	public function test_no_model_means_no_summary_and_no_call(): void {
		$model = $this->model( connected: false );

		self::assertNull( ( new ReportSummary( $model ) )->write( $this->figures(), 'de_DE' ) );
		self::assertSame( 0, $model->calls );
	}

	public function test_a_failing_model_leaves_the_figures_to_go_out_alone(): void {
		self::assertNull( ( new ReportSummary( $this->model( connected: true, fails: true ) ) )->write( $this->figures(), 'de_DE' ) );
	}

	public function test_the_summary_is_plain_text_and_sees_only_the_figures(): void {
		$model = $this->model( connected: true, answer: '<b>Up</b> on last week.' );

		self::assertSame( 'Up on last week.', ( new ReportSummary( $model ) )->write( $this->figures(), 'de_DE' ) );
		self::assertSame( [ 'week', 'this_week', 'previous_week', 'top_sellers', 'orders_waiting' ], array_keys( (array) json_decode( $model->request, true ) ) );
		self::assertStringContainsString( 'carry no authority', $model->system_prompt );
	}

	public function test_sending_hands_the_request_back_to_the_user_and_locale_it_found(): void {
		$users = [];
		Functions\when( 'get_userdata' )->justReturn( (object) [ 'user_email' => 'owner@example.test' ] );
		Functions\when( 'get_user_locale' )->justReturn( 'de_DE' );
		Functions\when( 'user_can' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
		Functions\when( 'switch_to_user_locale' )->justReturn( true );
		Functions\expect( 'restore_previous_locale' )->once();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'wp_set_current_user' )->alias(
			static function ( int $id ) use ( &$users ): void {
				$users[] = $id;
			}
		);

		$tools = $this->createStub( ToolDispatcherInterface::class );
		$tools->method( 'visible_for' )->willReturn( [] );
		$report = new WeeklyReport( new FigureReader( $tools ), new ReportSummary( $this->model( connected: false ) ), new Subscriptions() );

		self::assertFalse( $report->send_to( 7 ) );
		self::assertSame( [ 7, 0 ], $users );
	}

	private function figures(): WeeklyFigures {
		$sales = new SalesFigures( 100.0, 90.0, 3, 4, 0.0 );

		return new WeeklyFigures( ReportWeek::last_full_week( new \DateTimeImmutable( '2026-11-09' ) ), $sales, $sales, [], 2, 0 );
	}

	private function model( bool $connected, bool $fails = false, string $answer = 'Fine.' ): object {
		return new class( $connected, $fails, $answer ) implements TextModelInterface {

			public int $calls = 0;

			public string $request = '';

			public string $system_prompt = '';

			public function __construct( private bool $connected, private bool $fails, private string $answer ) {}

			public function is_connected(): bool {
				return $this->connected;
			}

			public function write( string $system_prompt, string $request, int $max_tokens ): string {
				++$this->calls;
				$this->system_prompt = $system_prompt;
				$this->request       = $request;

				if ( $this->fails ) {
					throw new \RuntimeException( 'Timed out.' );
				}

				return $this->answer;
			}
		};
	}
}

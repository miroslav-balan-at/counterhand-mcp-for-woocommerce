<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\WooCommerceTools\Domain\ContactFormAttachmentPolicy;
use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;

defined( 'ABSPATH' ) || exit;

/**
 * Contact Form 7's forms: /contact-form-7/v1/contact-forms.
 *
 * Its routes declare no arguments at all, so unlike every other resource here
 * the inputs are written down, from wpcf7_save_contact_form() and
 * get_contact_forms() as of CF7 6.2. Writes stay open, so a field a later release
 * adds can still be sent, and integrations.spec.ts runs against the latest release
 * to catch what a copy cannot. The feedback and refill routes stay out: they are
 * the public form submission path, not administration.
 */
final readonly class ContactForm7Descriptors implements DescriptorProvider {

	private const NAMESPACE = 'contact-form-7/v1';

	private const SUMMARY_FIELDS = [ 'id', 'slug', 'title', 'locale' ];

	private const ITEM_FIELDS = [ 'id', 'slug', 'title', 'locale', 'properties', 'config_errors' ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			new ResourceDescriptor(
				'contact_forms',
				IntegrationGroup::of( 'contact-form-7', copy: new ContactForm7Copy() ),
				RestRoute::of( self::NAMESPACE, '/contact-forms' ),
				RestRoute::of( self::NAMESPACE, '/contact-forms/{id}' ),
				'contact form',
				'contact forms',
				$this->operations()
			),
		];
	}

	/** @return list<OperationDescriptor> */
	private function operations(): array {
		return [
			new OperationDescriptor(
				ToolName::from( 'get_contact_forms' ),
				Operation::GetItems,
				new FieldProfile( [], self::SUMMARY_FIELDS, false ),
				description_override: 'List Contact Form 7 forms. Pages by offset and per_page; the response carries no totals, so keep going until a page comes back short. Submissions are not here: Contact Form 7 does not store them.',
				declared_arguments: [
					'per_page' => [
						'type'        => 'integer',
						'description' => 'How many forms to return.',
						'default'     => 20,
					],
					'offset'   => [
						'type'        => 'integer',
						'description' => 'How many forms to skip.',
					],
					'search'   => [
						'type'        => 'string',
						'description' => 'Limit to forms matching this text.',
					],
					'order'    => [
						'type' => 'string',
						'enum' => [ 'ASC', 'DESC' ],
					],
					'orderby'  => [
						'type'        => 'string',
						'description' => 'A WP_Query orderby value, such as title or date.',
					],
				]
			),
			new OperationDescriptor(
				ToolName::from( 'get_contact_form' ),
				Operation::GetItem,
				new FieldProfile( [], self::ITEM_FIELDS, false ),
				'properties holds the form template, both mail templates, the messages and the additional settings.'
			),
			new OperationDescriptor(
				ToolName::from( 'create_contact_form' ),
				Operation::CreateItem,
				new FieldProfile( [], self::ITEM_FIELDS ),
				'Anything you leave out starts from Contact Form 7\'s default template. config_errors in the response lists what its validator found wrong.',
				forced_params: [ 'context' => 'save' ],
				// The public feedback route can send mail from any form that exists,
				// so a new form is as risky as an edited one.
				requires_confirmation: true,
				policy: new ContactFormAttachmentPolicy(),
				declared_arguments: $this->writable_arguments()
			),
			new OperationDescriptor(
				ToolName::from( 'update_contact_form' ),
				Operation::UpdateItem,
				new FieldProfile( [], self::ITEM_FIELDS ),
				'config_errors in the response lists what its validator found wrong. mail, mail_2 and messages each replace the stored value whole, so read the form first and send every key you want to keep.',
				forced_params: [ 'context' => 'save' ],
				// Changing a recipient quietly redirects every future submission.
				requires_confirmation: true,
				policy: new ContactFormAttachmentPolicy(),
				declared_arguments: $this->writable_arguments()
			),
			new OperationDescriptor(
				ToolName::from( 'delete_contact_form' ),
				Operation::DeleteItem,
				new FieldProfile( [], [ 'deleted' ], false ),
				description_override: 'Permanently delete a Contact Form 7 form. There is no trash, and any page embedding it stops showing a form.',
				requires_confirmation: true
			),
		];
	}

	/** @return array<string, array<string, mixed>> */
	private function writable_arguments(): array {
		$mail = [
			'type'       => 'object',
			'properties' => [
				'subject'            => [ 'type' => 'string' ],
				'sender'             => [ 'type' => 'string' ],
				'recipient'          => [ 'type' => 'string' ],
				'body'               => [ 'type' => 'string' ],
				'additional_headers' => [ 'type' => 'string' ],
				'attachments'        => [ 'type' => 'string' ],
				'use_html'           => [ 'type' => 'boolean' ],
				'exclude_blank'      => [ 'type' => 'boolean' ],
			],
		];

		return [
			'title'               => [ 'type' => 'string' ],
			'locale'              => [
				'type'        => 'string',
				'description' => 'A WordPress locale, such as de_DE.',
			],
			'form'                => [
				'type'        => 'string',
				'description' => 'The form template, in Contact Form 7 form-tag syntax, such as [email* your-email].',
			],
			'mail'                => [
				'description' => 'The mail sent when the form is submitted. Sending this also switches it on.',
				...$mail,
			],
			'mail_2'              => [
				'description' => 'The optional second mail, usually an autoresponder. Stays off unless active is true.',
				'type'        => 'object',
				'properties'  => [
					'active' => [ 'type' => 'boolean' ],
					...$mail['properties'],
				],
			],
			'messages'            => [
				'type'                 => 'object',
				'description'          => 'The messages shown to the person filling in the form, by message id. Any id you leave out becomes blank, so send them all.',
				'additionalProperties' => [ 'type' => 'string' ],
			],
			'additional_settings' => [
				'type'        => 'string',
				'description' => 'One setting per line, as name: value.',
			],
		];
	}
}

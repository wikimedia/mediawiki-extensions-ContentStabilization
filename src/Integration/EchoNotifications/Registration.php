<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\EchoNotifications;

use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\Notifications\INotifier;

class Registration {

	/**
	 * Registers the notifications for content stabilization using Echo mechanism.
	 *
	 * @return void
	 */
	public static function registerNotifications() {
		/** @var INotifier $notifier */
		$notifier = MediaWikiServices::getInstance()->getService( 'MWStakeNotificationsNotifier' );

		$notifier->registerNotificationCategory(
			'content-stabilization-cat',
			[
				'tooltip' => 'content-stabilization-echo-cat-tooltip',
			]
		);

		$notifier->registerNotification(
			'content-stabilization-stabilized',
			[
				'category' => 'content-stabilization-cat',
				'summary-params' => [
					'title', 'agent', 'realname'
				],
				'email-subject-params' => [
					'title', 'agent', 'realname'
				],
				'email-body-params' => [
					'title', 'agent', 'realname'
				],
				'web-body-params' => [
					'title', 'agent', 'realname'
				],
				'summary-message' => 'content-stabilization-approval-notification-summary',
				'email-subject-message' => 'content-stabilization-approval-notification-subject',
				'email-body-message' => 'content-stabilization-approval-notification-email-body',
				'web-body-message' => 'content-stabilization-approval-notification-summary',
				'user-locators' => [ static::class . '::getAllSubscribed' ]
			]
		);
	}

	/**
	 * Retrieves the list of all users subscribed to content stabilization notifications.
	 *
	 * @return array The array of user IDs subscribed to content stabilization notifications.
	 */
	public static function getAllSubscribed(): array {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $dbr->select(
			"user_properties",
			"DISTINCT up_user",
			[
				"up_property" => [
					"echo-subscriptions-web-content-stabilization-cat",
					"echo-subscriptions-email-content-stabilization-cat"
				],
				"up_value" => 1
			],
			__METHOD__
		);
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$users = [];
		foreach ( $res as $row ) {
			$user = $userFactory->newFromId( $row->up_user );
			$users[$user->getId()] = $user;
		}

		return $users;
	}
}

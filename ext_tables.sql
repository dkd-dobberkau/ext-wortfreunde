CREATE TABLE tx_wortfreundeconnector_webhook_log (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,

    webhook_id varchar(255) DEFAULT '' NOT NULL,
    event_type varchar(50) DEFAULT '' NOT NULL,
    delivery_id varchar(255) DEFAULT '' NOT NULL,
    payload mediumtext,
    status varchar(20) DEFAULT 'pending' NOT NULL,
    error_message text,
    tt_content_uid int(11) unsigned DEFAULT '0' NOT NULL,
    page_uid int(11) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY webhook_id (webhook_id),
    KEY status (status),
    KEY event_type (event_type)
);

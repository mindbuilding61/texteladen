#
# Table structure for table 'tt_content' (custom fields for EXT:texteladen)
#
CREATE TABLE tt_content (
    tx_texteladen_folder text,
    tx_texteladen_tag varchar(255) DEFAULT '' NOT NULL,
    tx_texteladen_words_per_page int(11) DEFAULT '200' NOT NULL
);


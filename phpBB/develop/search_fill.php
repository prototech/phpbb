<?php

//
// Security message:
//
// This script is potentially dangerous.
// Remove or comment the next line (die(".... ) to enable this script.
// Do NOT FORGET to either remove this script or disable it after you have used it.
//

//
// Security message:
//
// This script is potentially dangerous.
// Remove or comment the next line (die(".... ) to enable this script.
// Do NOT FORGET to either remove this script or disable it after you have used it.
//
die("Please read the first lines of this script for instructions on how to enable it");

//
// Do not change anything below this line.
//
set_time_limit(0);

define('IN_PHPBB', true);
define('PHPBB_ROOT_PATH', './../');
define('PHP_EXT', substr(strrchr(__FILE__, '.'), 1));
include(PHPBB_ROOT_PATH . 'common.' . PHP_EXT);

// Start session management
phpbb::$user->session_begin();
phpbb::$acl->init(phpbb::$user->data);
phpbb::$user->setup();

$search_type = phpbb::$config['search_type'];

if (!file_exists(PHPBB_ROOT_PATH . 'includes/search/' . $search_type . '.' . PHP_EXT))
{
	trigger_error('NO_SUCH_SEARCH_MODULE');
}

require(PHPBB_ROOT_PATH . 'includes/search/' . $search_type . '.' . PHP_EXT);

$error = false;
$search = new $search_type($error);

if ($error)
{
	trigger_error($error);
}

print "<html>\n<body>\n";

//
// Fetch a batch of posts_text entries
//
$sql = "SELECT COUNT(*) as total, MAX(post_id) as max_post_id
	FROM ". POSTS_TABLE;
if ( !($result = phpbb::$db->sql_query($sql)) )
{
	$error = phpbb::$db->sql_error();
	die("Couldn't get maximum post ID :: " . $sql . " :: " . $error['message']);
}

$max_post_id = phpbb::$db->sql_fetchrow($result);

$totalposts = $max_post_id['total'];
$max_post_id = $max_post_id['max_post_id'];

$postcounter = (!isset($HTTP_GET_VARS['batchstart'])) ? 0 : $HTTP_GET_VARS['batchstart'];

$batchsize = 200; // Process this many posts per loop
$batchcount = 0;
for(;$postcounter <= $max_post_id; $postcounter += $batchsize)
{
	$batchstart = $postcounter + 1;
	$batchend = $postcounter + $batchsize;
	$batchcount++;

	$sql = "SELECT *
		FROM " . POSTS_TABLE . "
		WHERE post_id
			BETWEEN $batchstart
				AND $batchend";
	if( !($result = phpbb::$db->sql_query($sql)) )
	{
		$error = phpbb::$db->sql_error();
		die("Couldn't get post_text :: " . $sql . " :: " . $error['message']);
	}

	$rowset = phpbb::$db->sql_fetchrowset($result);
	phpbb::$db->sql_freeresult($result);

	$post_rows = sizeof($rowset);

	if( $post_rows )
	{

	// $sql = "LOCK TABLES ".POST_TEXT_TABLE." WRITE";
	// $result = phpbb::$db->sql_query($sql);
		print "\n<p>\n<a href='{$_SERVER['PHP_SELF']}?batchstart=$batchstart'>Restart from posting $batchstart</a><br>\n";

		// For every post in the batch:
		for($post_nr = 0; $post_nr < $post_rows; $post_nr++ )
		{
			print ".";
			flush();

			$post_id = $rowset[$post_nr]['post_id'];

			$search->index('post', $rowset[$post_nr]['post_id'], $rowset[$post_nr]['post_text'], $rowset[$post_nr]['post_subject'], $rowset[$post_nr]['poster_id']);
		}
	// $sql = "UNLOCK TABLES";
	// $result = phpbb::$db->sql_query($sql);

	}
}

print "<br>Removing common words (words that appear in more than 50% of the posts)<br>\n";
flush();
$search->tidy();
print "Removed words that where too common.<br>";

echo "<br>Done";

?>

</body>
</html>

<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/CSV.class.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: Export retweet chain</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: Export retweet chain</h1>

        <?php
        validate_all_variables();
        $collation = current_collation();
        $min_nr_of_nodes = (isset($_GET['minf']) && is_numeric($_GET['minf'])) ? $min_nr_of_nodes = $_GET['minf'] : 4;

        // make filename and open file for write
        $module = "retweets_chain";
        $exportSettings = array();
        if (isset($_GET['exportSettings']) && $_GET['exportSettings'] != "")
            $exportSettings = explode(",", $_GET['exportSettings']);
        $exportSettings[] = $min_nr_of_nodes;
        $filename = get_filename_for_export($module, implode("_", $exportSettings));
        $csv = new CSV($filename, $outputformat);

        // write header
        $header = "id,time,created_at,from_user_name,text,filter_level,possibly_sensitive,withheld_copyright,withheld_scope,truncated,favorite_count,lang,to_user_name,in_reply_to_status_id,source,location,lat,lng,from_user_id,from_user_realname,from_user_verified,from_user_description,from_user_url,from_user_profile_image_url,from_user_utcoffset,from_user_timezone,from_user_lang,from_user_followercount,from_user_friendcount,from_user_favourites_count,from_user_listed,from_user_withheld_scope,from_user_created_at";
        if (array_search("urls", $exportSettings) !== false)
            $header .= ",urls,urls_expanded,urls_followed,domains,HTTP status code, url_is_media_upload";
        if (array_search("mentions", $exportSettings) !== false)
            $header .= ",mentions";
        if (array_search("hashtags", $exportSettings) !== false)
            $header .= ",hashtags";
        $csv->writeheader(explode(',', $header));

        // get identical tweets
        $sql = "SELECT text COLLATE $collation as text, COUNT(text COLLATE $collation) AS count FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY text HAVING count >= " . $min_nr_of_nodes . " ORDER BY count DESC";
        $rec = mysql_query($sql);
        
        print mysql_num_rows($rec) . " retweet chains found with more than " . $min_nr_of_nodes . " tweets<br>";
        flush();

        while ($res = mysql_fetch_assoc($rec)) {

            $text = $res['text'];

            // list other occurences
            $sql3 = "SELECT * FROM " . $esc['mysql']['dataset'] . "_tweets WHERE text COLLATE $collation = '" . mysql_real_escape_string($text) . "' ORDER BY created_at ASC";

            $rec3 = mysql_query($sql3);
            if ($rec3) {
                while ($data = mysql_fetch_assoc($rec3)) {
                    $csv->newrow();
                    if (preg_match("/_urls/", $sql))
                        $id = $data['tweet_id'];
                    else
                        $id = $data['id'];
                    $csv->addfield($id);
                    $csv->addfield(strtotime($data["created_at"]));
                    $fields = array( 'created_at', 'from_user_name', 'text', 'filter_level', 'possibly_sensitive', 'withheld_copyright', 'withheld_scope', 'truncated', 'favorite_count', 'lang', 'to_user_name', 'in_reply_to_status_id', 'source', 'location', 'geo_lat', 'geo_lng', 'from_user_id', 'from_user_realname', 'from_user_verified', 'from_user_description', 'from_user_url', 'from_user_profile_image_url', 'from_user_utcoffset', 'from_user_timezone', 'from_user_lang', 'from_user_followercount', 'from_user_friendcount', 'from_user_favourites_count', 'from_user_listed', 'from_user_withheld_scope', 'from_user_created_at' );
                    foreach ($fields as $f) {
                        $csv->addfield(isset($data[$f]) ? $data[$f] : ''); 
                    }
                    if (array_search("urls", $exportSettings) !== false) {
                        $urls = $expanded = $followed = $domain = "";
                        $sql2 = "SELECT url, url_expanded, url_followed, domain, error_code, url_is_media_upload FROM " . $esc['mysql']['dataset'] . "_urls WHERE tweet_id = " . $data['id'];
                        $rec2 = mysql_query($sql2);
                        $urls = $expanded = $followed = $domain = $error = $media = array();
                        if (mysql_num_rows($rec2) > 0) {
                            while ($res2 = mysql_fetch_assoc($rec2)) {
                                $urls[] = $res2['url'];
                                $expanded[] = $res2['url_expanded'];
                                $followed[] = $res2['url_followed'];
                                $domain[] = $res2['domain'];
                                $error[] = $res2['error_code'];
                                $media[] = $res2['url_is_media_upload'];
                            }
                        }
                        $csv->addfield(implode("; ", $urls));
                        $csv->addfield(implode("; ", $expanded));
                        $csv->addfield(implode("; ", $followed));
                        $csv->addfield(implode("; ", $domain));
                        $csv->addfield(implode("; ", $error));
                        $csv->addfield(implode("; ", $media));
                    }
                    if (array_search("mentions", $exportSettings) !== false) {
                        $sql2 = "SELECT * FROM " . $esc['mysql']['dataset'] . "_mentions WHERE tweet_id = " . $id;
                        $rec2 = mysql_query($sql2);
                        $mentions = array();
                        if (mysql_num_rows($rec2) > 0) {
                            while ($res2 = mysql_fetch_assoc($rec2)) {
                                $mentions[] = $res2['to_user'];
                            }
                        }
                        $csv->addfield(implode("; ", $mentions));
                    }
                    if (array_search("hashtags", $exportSettings) !== false) {
                        $sql2 = "SELECT * FROM " . $esc['mysql']['dataset'] . "_hashtags WHERE tweet_id = " . $id;
                        $rec2 = mysql_query($sql2);
                        $hashtags = array();
                        if (mysql_num_rows($rec2) > 0) {
                            while ($res2 = mysql_fetch_assoc($rec2)) {
                                $hashtags[] = $res2['text'];
                            }
                        }
                        $csv->addfield(implode("; ", $hashtags));
                    }
                    $csv->writerow();
                }
            }
        }
        $csv->close();
        
        echo '<fieldset class="if_parameters">';
        echo '<legend>Your File</legend>';
        echo '<p><a href="' . filename_to_url($filename) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        
        ?>

    </body>
</html>

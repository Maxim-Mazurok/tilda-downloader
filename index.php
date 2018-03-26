<?php
/**
 * Created by Maxim Mazurok <maxim@mazurok.com>
 * Date: 3/26/18
 * Time: 8:01 PM
 */

$tilda_url = 'http://project550091.tilda.ws';
$new_site_url = 'https://popcorn-terminator.herokuapp.com';

create_output_dir();

$HTML = file_get_contents($tilda_url);

/* scripts */
$scripts = extract_scripts($HTML);
foreach ($scripts as $url) {
    save_resource($url);
}
$HTML = replace_resources($HTML, $scripts);

/* css */
$links = extract_css($HTML);
foreach ($links as $url) {
    save_resource($url);
}
$HTML = replace_resources($HTML, $links);

/* images */
$images = extract_images($HTML);
foreach ($images as $url) {
    save_resource($url);
}
$HTML = replace_resources($HTML, $images);

/* css images */
$css_images = extract_css_backgrounds($HTML);
foreach ($css_images as $url) {
    save_resource($url);
}
$HTML = replace_resources($HTML, $css_images);

/* og:image */
// <meta property="og:image" content="https://static.tildacdn.com/tild3366-3534-4633-b831-623764306565/popcorn.jpg"/>


$HTML = remove_all_other($HTML, $tilda_url, $new_site_url);

/* save html file */
file_put_contents(dr('saved') . DIRECTORY_SEPARATOR . 'index.html', $HTML);


function remove_all_other($HTML, $tilda_url, $new_site_url)
{
    /* remove dns-prefetch */
    $HTML = preg_replace('/<link.*?rel="dns-prefetch".*?href="(.*?)".*?>/', '', $HTML);

    /* remove link rel="canonical" */
    $HTML = preg_replace('/<link.*?rel="canonical".*?href="(.*?)".*?>/', '', $HTML);

    /* remove favicon */
    $HTML = preg_replace('/<link.*?rel=".*?icon.*?".*?href="(.*?)".*?>/', '', $HTML);

    /* remove stats */
    $HTML = substr($HTML, 0, strpos($HTML, '<!-- Tilda copyright. Don\'t remove this line -->')) . '</body></html>';

    /* replace url */
    $HTML = str_replace($tilda_url, $new_site_url, $HTML);

    return $HTML;
}

function replace_resources($HTML, $resources)
{
    foreach ($resources as $url) {
        $path = get_res_local_path($url, true);
        $HTML = str_replace($url, $path, $HTML);
    }
    return $HTML;
}

function get_res_local_path($url, $relative = false)
{
    $basename = get_resource_basename($url);
    $dir = pathinfo(parse_url($url)['path'])['dirname'];
    if ($relative) {
        return $dir . DIRECTORY_SEPARATOR . $basename;
    } else {
        return dr('saved') . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $basename;
    }
}

function save_resource($url)
{
    echo '.';

    $url = preg_replace('/^\/\//', 'https://', $url);
    $content = file_get_contents($url);

    if (pathinfo(parse_url($url)['path'], PATHINFO_EXTENSION) === 'css') {
        $css_images = extract_css_backgrounds($content);
        foreach ($css_images as $img_url) {
            save_resource($img_url);
        }
        $content = replace_resources($content, $css_images);
    }

    if (!file_exists(pathinfo(get_res_local_path($url))['dirname'])) {
        mkdir(pathinfo(get_res_local_path($url))['dirname'], 0777, true);
    }

    file_put_contents(get_res_local_path($url), $content);
}

function get_resource_basename($url)
{
    $parsed_url = parse_url($url);
    $parsed_url_path = $parsed_url['path'];
    $url_path_info = pathinfo($parsed_url_path);
    $basename = $url_path_info['basename'];
    return $basename;
}

function extract_scripts($HTML)
{
    $re = '/<script.*?src="(.*?)"/';
    preg_match_all($re, $HTML, $matches, PREG_SET_ORDER, 0);
    return array_map(function ($x) {
        return $x[1];
    }, $matches);
}

function extract_css_backgrounds($CODE)
{
    $re = '/url\(\\\'(\\\' | \")?(.*?)\1?\)/';
    preg_match_all($re, $CODE, $matches, PREG_SET_ORDER, 0);

    $matches = array_map(function ($x) {
        $link = $x[2];
        $link = preg_replace('/(\'|")$/', '', $link);
        return $link;
    }, $matches);

    $matches = array_filter($matches, function ($x) {
        return !empty($x[2]);
    });

    return $matches;
}

function extract_css($HTML)
{
    $re = '/<link.*?href="(.*?)"/';
    preg_match_all($re, $HTML, $matches, PREG_SET_ORDER, 0);

    $matches = array_filter($matches, function ($x) {
        return strpos($x[0], 'rel="stylesheet"') !== false;
    });

    $matches = array_map(function ($x) {
        return $x[1];
    }, $matches);

    return $matches;
}

function extract_images($HTML)
{
    $re = '/="(.*?)"/';
    preg_match_all($re, $HTML, $matches, PREG_SET_ORDER, 0);

    $matches = array_filter($matches, function ($x) {
        return preg_match('/\.(png|jpg|jpeg|gif|png|svg)$/', $x[1]) === 1;
    });

    $matches = array_map(function ($x) {
        return $x[1];
    }, $matches);

    return $matches;
}

function dr($name)
{
    $saved = __DIR__ . DIRECTORY_SEPARATOR . 'saved';

    switch ($name) {
        case 'saved':
            return $saved;
            break;
        default:
            die("unknown dir: \"$name\"!!!");
            break;
    }
}

function create_output_dir()
{
    if (!file_exists(dr('saved'))) mkdir(dr('saved'));
}

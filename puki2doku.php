<?php
#!/usr/bin/perl
#*****************************************************************************
# PukiWiki => DokuWiki data convertor
#
# Usage: puki2doku.php -s pukiwiki/wiki -d dokuwiki/data/page
#                     [-S/--font-size]
#                     [-C/--font-color]
#                     [-I/--indexmenu]
#                     [-N/--ignore-unknown-macro]
#                     [-O/--do-not-overwrite]
#                     [-D/--decode]
#                     [-A/--attach]
#                     [-H/--use-heading]
#                     [-P pagename.txt(encoded)/--page=pagename.txt(encoded)]
#                     [-E utf-8/--encoding=utf-8]
#
#*****************************************************************************
/*use strict;
use warnings;
use utf-8;
use Encode;
use File::Basename;
use File::Copy;
use File::Path;
use IO::File;
use Getopt::Long qw(:config no_ignore_case bundling);
use Cwd;*/

# If you need.
# binmode STDOUT, ":utf-8";


# ＿・ と全角数字は記号扱いじゃない
$KIGO_ARRAY = [
    '、',
    '。',
    '／',
    '　',
    '！',
    '＃',
    '＄',
    '％',
    '＆',
    '＋',
    '（',
    '）',
    '＝',
    '＊',
    '「',
    '」',
    '『',
    '』',
    '＠',
    '【',
    '】',
    '×',
    '→',
    '―', # dash
    '‐', # hypen, minus
    '～', # wave dash
];
$KIGO_STR = implode("", $KIGO_ARRAY);

$verbose = '';
$use_font_color_plugin = false;
$use_font_size_plugin = false;
$use_indexmenu_plugin = false;
$dst_dir = "./pages"; # "-s"オプションが省略された場合に備えて
$decode_mode = '';
$attach_file_mode = '';
$src_dir = ".";
$ignore_unknown_macro = '';
$dont_overwrite = '';
$specified_page_file = '';
$input_encoding = "utf-8";
$use_heading = '';
$destLang = 'ja';

$smiles = [
    'smile' => ' :-) ',
    'bigsmile' => ' LOL ',
    'huh' => ' :-P ',
    'oh' => ' :-/ ',
    'wink' => ' ;-) ',
    'sad' => ' :-( ',
    'worried' => ' :-| ',
];

/*GetOptions("verbose|v"     => \$verbose,
           "font-color|C"  => \$use_font_color_plugin,
           "font-size|S"   => \$use_font_size_plugin,
           "indexmenu|I"   => \$use_indexmenu_plugin,
           "dst-dir|d=s"   => \$dst_dir,
           "decode|D"      => \$decode_mode,
           "attach|A"      => \$attach_file_mode,
           "src-dir|s=s"   => \$src_dir,
           "page|P=s"      => \$specified_page_file,
           "help|h"        => \&usage,
           "ignore-unknown-macro|N" => \$ignore_unknown_macro,
           "do-not-overwrite|O" => \$dont_overwrite,
           "encoding|E=s"  => \$input_encoding,
           "use-heading|H"  => \$use_heading,
) || usage();*/

$shortopts = "";
$shortopts .= "v"; // verbose
$shortopts .= "C"; // use_font_color_plugin
$shortopts .= "S"; //use_font_size_plugin,
$shortopts .= "I"; //use_indexmenu_plugin,
$shortopts .= "d:";  // destination-dir
$shortopts .= "D::"; //decode_mode,
$shortopts .= "A"; //attach_file_mode,
$shortopts .= "s:";  // source-dir
$shortopts .= "P::"; // specified_page_file
$shortopts .= "h"; //usage,
$shortopts .= "N"; //ignore_unknown_macro,
$shortopts .= "O"; //dont_overwrite,
$shortopts .= "E::"; //input_encoding,
$shortopts .= "H"; //use_heading,


$longopts = [
    'verbose',
    'font-color',
    'font-size',
    'indexmenu',
    'dst-dir:',
    'decode::',
    'attach',
    'src-dir:',
    'page::',
    'help',
    'ignore-unknown-macro',
    'do-not-overwrite',
    'encoding::',
    'use-heading',
];
$options = getopt($shortopts, $longopts);
if (
    empty($options)
    || isset($options['h'])
    || isset($options['help'])
) {
    usage();
}


/*if ($decode_mode) {
    while (<>) {
        print $_;
        s/[\r\n]+$//;
        print encode("utf-8", decode($input_encoding, pukiwiki_filename_decode($_))),"\n";
    }
    exit;
}*/

$input_encoding = $options['encoding'] ?? null;
if (empty($input_encoding)) {
    $input_encoding = $options['E'] ?? null;
}
if (empty($input_encoding)) {
    $input_encoding = 'utf-8';
}

$decode_mode = $options['decode'] ?? null;
$decode_mode = $options['D'] ?? null;

if (!is_null($decode_mode)) {

    $fpr = fopen($decode_mode, 'r');
    while ($line = fgets($fpr)) {
        $line = trim($line);
        echo mb_convert_encoding(pukiwiki_filename_decode($line), 'utf-8', $input_encoding) . PHP_EOL;

    }
    fclose($fpr);
    exit;
}

/*if (! -d $dst_dir) {
    warn "$dst_dir is not exist\n";
    exit 2;
}
elsif (! -w $dst_dir) {
    warn "$dst_dir is not writable\n";
    exit 3;
}*/

$dst_dir = $options['d'] ?? null;
if (empty($dst_dir)) {
    $dst_dir = $options['dst-dir'] ?? null;

}

if (!is_dir($dst_dir)) {
    echo $dst_dir . " is not exist" . PHP_EOL;
    exit;
} elseif (!is_writable($dst_dir)) {
    echo $dst_dir . " is not writable" . PHP_EOL;
    exit;
}

$src_dir = $options['s'] ?? null;
if (empty($src_dir)) {
    $src_dir = $options['src-dir'] ?? null;

}

if (!is_dir($src_dir)) {
    echo $src_dir . " is not exist" . PHP_EOL;
    exit;
} elseif (!is_readable($src_dir)) {
    echo $src_dir . " is not readable" . PHP_EOL;
    exit;
}


# $src_dirにchdirするので，絶対パスが必要．
//$dst_dir=Cwd::abs_path($dst_dir);
$dst_dir = realpath($dst_dir);

#-----------------------------------------------------------------------------

/*chdir($src_dir) || die "can't chdir $src_dir: $!";
my $d;
opendir($d, ".") || die "can't opendir: $src_dir: $!";

if ($attach_file_mode) {
    while (my $file = readdir($d)) {
        next if (-d $file || $file =~ /\.log$/ || $file eq "index.html" || $file eq ".htaccess");
        print $file,"\n" if ($verbose);
        copy_attach_file($file);
    }
}
else {
    while (my $file = readdir($d)) {
        next if (-d $file || $file !~ /\.txt$/);
        next if ($specified_page_file && $specified_page_file ne $file);
        print $file,"\n" if ($verbose);
        convert_file($file);
    }
}

closedir($d);*/

$attach_file_mode = $options['A'] ?? null;
$attach_file_mode = $options['attach'] ?? null;

$verbose = $options['v'] ?? null;
$verbose = $options['verbose'] ?? null;

$specified_page_file = $options['P'] ?? null;
$specified_page_file = $options['page'] ?? null;

$use_font_color_plugin = isset($options['C']);
if (!$use_font_color_plugin) {
    $use_font_color_plugin = isset($options['font-color']);
}
// fontsize2 Plugin
// https://www.dokuwiki.org/plugin:fontsize2
$use_font_size_plugin = isset($options['S']);
if (!$use_font_size_plugin) {
    $use_font_size_plugin = isset($options['font-size']);
}

var_dump($options);
var_dump($use_font_color_plugin);
var_dump($use_font_size_plugin);

if ($attach_file_mode === true) {
    foreach (glob($src_dir) as $filename) {
        if (is_file($filename)) {
            if ($verbose) {
                echo $filename . PHP_EOL;
            }
            copy_attach_file($filename);
        }
    }
} else {
    echo 'convert from ' . $src_dir . PHP_EOL;
//    var_dump(realpath($src_dir).'/*.txt');
//    var_dump(glob(realpath($src_dir).'/*.txt'));
    foreach (glob($src_dir . '/*.txt') as $filename) {
        echo $filename . PHP_EOL;
        if (
        is_file($filename)
//            && preg_match('/\.txt$/ui', $filename)
        ) {
            if (!is_null($specified_page_file) && $filename !== $specified_page_file) {
                continue;
            }
            convert_file($filename);
        }
    }
}

exit;

#-----------------------------------------------------------------------------

function usage()
{
    print "Usage: $0 [-v] [-s dir] [-d dir] *.txt\n";
    print "       [--font-color/-C]\n";
    print "       [--font-size/-S]\n";
    print "       [--indexmenu/-I]\n";
    print "       [--ignore-unknown-macro/-N]\n";
    print "       [--do-not-overwrite/-O]\n";
    print "       [--decode/-D]\n";
    print "       [--attach/-A]\n";
    print "       [--use-heading/-H]\n";
    print "       [--page=pagename.txt/-P pagename.txt]\n";
    print "       [--encoding=utf-8/-E utf-8]\n";
    exit;
}

/**
 * Decode page name
 * @param string $str
 * @return string
 */
function pukiwiki_filename_decode($str)
{
    $path = pathinfo($str);
//    var_dump(pkwk_hex2bin($str));
    $path['filename'] = pkwk_hex2bin($path['filename']);
    var_dump($path);

    return $path['dirname'] . DIRECTORY_SEPARATOR . $path['filename'] . '.' . $path['extension'];
//    return pkwk_hex2bin($str);
}

/**
 * Inversion of bin2hex()
 * @param string $hex_string
 * @return string
 */
function pkwk_hex2bin($hex_string)
{
    // preg_match : Avoid warning : pack(): Type H: illegal hex digit ...
    // (string)   : Always treat as string (not int etc). See BugTrack2/31
    return preg_match('/^[0-9a-f]+$/i', $hex_string) ?
        pack('H*', (string)$hex_string) : $hex_string;
}

/**
 * @param $src_file
 */
function copy_attach_file($src_file)
{
    global $dst_dir, $dont_overwrite, $verbose;
//    my ($src_file) = @_;

    $src_filename = basename($src_file);

    # {full_pagename}_{attached_filename} (pagename には / を含む)
//    my ($full_pagename, $attached_filename) = split(/_/, $src_filename, 2);
    list ($full_pagename, $attached_filename) = explode('_', $src_filename, 2);

    $dokuwiki_subdir = convert_filename($full_pagename);
    $dokuwiki_filename = convert_filename($attached_filename);

//    my $media_dst_dir = join("/", $dst_dir, $dokuwiki_subdir);
    $media_dst_dir = implode("/", [$dst_dir, $dokuwiki_subdir]);
//    if (! -d $media_dst_dir) {
    if (!is_dir($media_dst_dir)) {
//        mkpath($media_dst_dir) || die "can't mkdir $media_dst_dir: $!";
        if (mkdir($media_dst_dir)) {
            echo "can't mkdir " . $media_dst_dir . PHP_EOL;
            exit;
        }

    }

//    my $dst_file = join("/", $media_dst_dir, $dokuwiki_filename);
    $dst_file = implode(DIRECTORY_SEPARATOR, [$media_dst_dir, $dokuwiki_filename]);

    # 既に存在していたら上書きしない
//    if ($dont_overwrite && -f $dst_file) {
    if ($dont_overwrite && is_file($dst_file)) {
//        print "SKIP " . encode("utf-8", $dst_file),"\n" if ($verbose);
        if ($verbose) {
            echo "SKIP " . mb_convert_encoding($dst_file, "utf-8") . PHP_EOL;
        }

        return;
    }

//    printf "%s => %s\n", encode("utf-8", $src_file), encode("utf-8", $dst_file) if ($verbose);
    if ($verbose) {
        printf("%s => %s" . PHP_EOL, mb_convert_encoding($src_file, "utf-8"), mb_convert_encoding($dst_file, "utf-8"));
    }
    if (copy($src_file, $dst_file)) {
        echo 'copied : ' . mb_convert_encoding($src_file, "utf-8") . PHP_EOL;
    }
}

/*sub copy_attach_file {
    my ($src_file) = @_;

    my $src_filename = basename($src_file);

    # {full_pagename}_{attached_filename} (pagename には / を含む)
    my ($full_pagename, $attached_filename) = split(/_/, $src_filename, 2);

    my $dokuwiki_subdir = convert_filename($full_pagename);
    my $dokuwiki_filename = convert_filename($attached_filename);

    my $media_dst_dir = join("/", $dst_dir, $dokuwiki_subdir);
    if (! -d $media_dst_dir) {
        mkpath($media_dst_dir) || die "can't mkdir $media_dst_dir: $!";
    }

    my $dst_file = join("/", $media_dst_dir, $dokuwiki_filename);

    # 既に存在していたら上書きしない
    if ($dont_overwrite && -f $dst_file) {
        print "SKIP " . encode("utf-8", $dst_file),"\n" if ($verbose);
        return;
    }

    printf "%s => %s\n", encode("utf-8", $src_file), encode("utf-8", $dst_file) if ($verbose);

    copy($src_file, $dst_file);
}*/


function convert_file($src_file = '')
{
    global $input_encoding, $dst_dir, $dont_overwrite, $verbose, $use_heading, $use_indexmenu_plugin, $ignore_unknown_macro, $destLang;
    //    my ($src_file) = @_;

    $in_subdir = 0;
//    $last_line = "";

    echo 'converting... ' . PHP_EOL;

//    my $r = new IO::File $src_file, "r";
    $r = fopen($src_file, 'r');

    //ファイルの更新時刻を取得しておく
    $fileModified = filemtime($src_file);

//        my $dokuwiki_filename = convert_filename($src_file);
    $dokuwiki_filename = convert_filename($src_file);
//    if ($dokuwiki_filename =~ /\//) {
    if (strpos($dokuwiki_filename, "/") !== false) {
        $in_subdir = 1;
    }

    # 小文字にしたり、記号を変換してないページ名
//    my $pagename = decode($input_encoding, pukiwiki_filename_decode($src_file));
    $pagename = mb_convert_encoding(pukiwiki_filename_decode($src_file), 'utf-8', $input_encoding);

//    return if ($pagename =~ /^:/); # 特殊ファイル
    if (preg_match('/^:/ui', $pagename)) {
        return false;
    } # 特殊ファイル

//    $pagename =~ s/\.txt//;
    $pagename = strtr($pagename, '.txt', '');
//    $pagename =~ s/\//:/g; # namespace の区切りは / ではなく :
    $pagename = strtr($pagename, '/', ':');

    /*    my $doku_file = sprintf "%s/%s",
                                $dst_dir,
                                $dokuwiki_filename;*/
    $doku_file = sprintf("%s" . DIRECTORY_SEPARATOR . "%s",
        $dst_dir,
        preg_replace("#/#ui", DIRECTORY_SEPARATOR, $dokuwiki_filename));

    # 既に存在していたら上書きしない
//    if ($dont_overwrite && -f $doku_file) {
    if ($dont_overwrite && is_file($doku_file)) {
//        print "SKIP " . encode("utf-8", $doku_file),"\n" if ($verbose);
        if ($verbose) {
            echo "SKIP " . mb_convert_encoding($doku_file, "utf-8") . PHP_EOL;
        }

        return false;
    }

//    my $doku_file_dir = dirname($doku_file);
    $doku_file_dir = dirname($doku_file);
//    if (! -d $doku_file_dir) {
    if (!is_dir($doku_file_dir)) {
//        mkpath($doku_file_dir);
        if (!mkdir($doku_file_dir . DIRECTORY_SEPARATOR, 0777, true)) {
            echo "can't make directly : " . $doku_file_dir . PHP_EOL;

            return false;
        }
    }

    $pre = 0;
    $prettify = 0;
//    my @sp_buf = (); # #contents
    $sp_buf = []; # #contents

//    my @doku_lines = ();
    $doku_lines = [];

    # 見出しを追加。これはDokuWikiでuseheadingオプションを使う場合に有効
    if ($use_heading) {
        $pageid = $pagename;
//        $pageid =~ tr/[A-ZＡ-Ｚ]/[a-zａ-ｚ]/; # pageidは小文字
        $pageid = strtolower($pageid);
//        push @doku_lines, "====== " . $pageid . " ======\n\n";
        array_push($doku_lines, "====== " . $pageid . " ======" . PHP_EOL . PHP_EOL);
    }

//    while (my $line = <$r>) {
    //1行ずつ読み込む
    $multiLangFlag = null;
    while ($line = fgets($r)) {
//    $line = decode($input_encoding, $line);
        $line = mb_convert_encoding($line, 'utf-8', $input_encoding);
//        $line =~ s/[\r\n]+$//;
        $line = preg_replace("/[\r\n]+\$/ui", '', $line);

        //国際化されていたらjaのみ取り出す
        if (preg_match('/#multilang\(([a-zA-Z_]{2,8})\)[\{]{2,3}/ui', $line, $langMatches)) {
            $multiLangFlag = $langMatches[1];
            continue;
        }
        if ($multiLangFlag == $destLang && preg_match('/[\}]{2,3}/',$line)) {
            $multiLangFlag = null;
            continue;
        }
        //他の言語なら読み飛ばす
        if ($multiLangFlag !== null && $multiLangFlag !== $destLang) {
            while ($line !== false && !preg_match('/[\}]{2,3}/',$line)) {
                $line = fgets($r);
            }
            $multiLangFlag = null;
            continue;
        }

        # ----
        # #contents
//        if ($line eq "----" && scalar(@sp_buf) == 0) {
        if (preg_match("/^----/ui", $line) && count($sp_buf) == 0) {
//            push @sp_buf, $line;
            array_push($sp_buf, $line);
//            next;
            continue;
        } //        elsif($line eq "----" && scalar(@sp_buf) == 2) {
        elseif (preg_match("/^----/ui", $line) && count($sp_buf) == 2) {
//            @sp_buf = ();
            $sp_buf = [];
//            next;
            continue;
        } //        elsif($line eq "#contents" && scalar(@sp_buf) == 1) {
        elseif (preg_match(/** @lang RegExp */
                "/^#contents/ui", $line) && count($sp_buf) == 1
        ) {
            array_push($sp_buf, $line);
//            next;
            continue;
            //コメント行
        } elseif (preg_match(/** @lang RegExp */
            "!^//.*$!ui", $line)) {
            continue;
        } else {
//            foreach (@sp_buf) {
            foreach ($sp_buf as $sp) {
//                push @doku_lines, $_ . "\n";
                array_push($doku_lines, $sp . "\r\n");
            }
//            @sp_buf = ();
            $sp_buf = [];
        }
        # ----


        if ($use_indexmenu_plugin) {
//            $line = ~s /^#ls2?\((.*)\).*$/convert_ls_indexmenu($pagename, $1)/e;
            preg_replace_callback(/** @lang RegExp */
                "/^#ls2?\((.*)\).*$/ui", function ($matches) use ($pagename) {
                return convert_ls_indexmenu($pagename, $matches[1]);
            }, $line);

//            $line = ~s /^#ls2?$/convert_ls_indexmenu($pagename)/e;
            preg_replace("/^#ls2?$/ui", convert_ls_indexmenu($pagename), $line);
        }

        # prettify etention
//        if ($line = ~ /^#prettify{{/) {
        if (preg_match("/^#prettify{{/", $line)) {
//            push @doku_lines, "<code>\n" if (!$pre) ;
            if (!$pre) {
                array_push($doku_lines, "<code>");
            }
            $prettify = 1;
//            next;
            continue;
        } //        elsif($prettify){
        elseif ($prettify) {
//            if ($line = ~ /^\
//                }\
//            }/) {
            if (preg_match(/** @lang RegExp */
                "/^\}\}/ui"

                /*            if (preg_match(<<<REGEXP
                /^\
                                }\
                            }/ui
                REGEXP*/
                , $line)) {
//    push @doku_lines, "</code>\n";
                array_push($doku_lines, "</code>\r\n");
                $prettify = 0;
            } else {
//                push @doku_lines, $line . "\n";
                array_push($doku_lines, $line . "\r\n");
            }
            continue;
        }

//        if ($line = ~s /^\x20// || $line =~ /^\t/) {
        if (preg_match("/^[\x20\t]+/ui", $line)) {
            if (!$pre) {
//            if (scalar(@doku_lines) && $doku_lines[-1] = ~ /^\s + \- /) {
                if (count($doku_lines) && preg_match("/^\s + \- /ui", end($doku_lines))) {
//                $doku_lines[-1] = ~s / \n$//;
                    preg_replace("/ \n$/ui", '', $doku_lines[get_last_key($doku_lines)]);
                }
//                push @doku_lines, "<code>\n";
                array_push($doku_lines, "<code>\r\n");
            }
//            push @doku_lines, $line . "\n";
            array_push($doku_lines, $line . "\r\n");
            $pre = 1;
//            next;
            continue;
        } elseif ($pre) {
//        push @doku_lines, "</code>\n";
            array_push($doku_lines, "</code>\r\n");
            $pre = 0;
        }

//        if ($line = ~ /^\- + $/) {
        //段落記号のみの行
        if (preg_match(/** @lang RegExp */
            "/^[\s\-*]+$/ui", $line)) {
//            push @doku_lines, $line . "\n";
//            array_push($doku_lines, $line . "\r\n");
            array_push($doku_lines, "\r\n");
//            next;
            continue;
        }
        //文字修飾の半分のみはhtml変換に失敗する
        //イタリック指示の半分のみを全角に置換
        if (mb_substr_count($line, '//') % 2 == 1) {
            $line = preg_replace("![^:](//)!ui", '**Not pair slashes**', $line, 1);
        }


        # ref
//        $line = ~s / \&ref\((.+?)\);/convert_ref($pagename, $1)/ge;
//        $line = ~s /#ref\((.+?)\)/convert_ref($pagename, $1)/ge;
        preg_replace_callback(/** @lang RegExp */
            "/[\&#].ref\((.+?)\);/ui", function ($matches) use ($pagename) {
            return convert_ref($pagename, $matches[1]);
        }, $line);


//            next if ($line = ~ /^#/ && $ignore_unknown_macro);
        if (preg_match("/^#/ui", $line) && $ignore_unknown_macro) {
            continue;
        }

        # definitions
//        $line = ~s /^:(.*?)\|(.*)$/  = $1 : $2 /;
        $line = preg_replace(/** @lang RegExp */
            "/^:(.*?)\|(.*)$/ui", "  = $1 : $2 ", $line);
        /*        $line = preg_replace_callback("/^:(.*?)\|(.*)$/ui", function ($matches) {
                    if (!isset($matches[1])) {
                        return "";
                    }
                    if (!isset($matches[2])) {
                        return "  = " . $matches[1] . " :  ";
                    }
                    return "  = " . $matches[1] . " : " . $matches[2] . " ";

                }, $line);*/

        # 装飾を削る (2回なのは入れ子対応、3回やっとく？)
//        $line = ~s / \&(\w +)\(([^\(\)]+?)\){
//                ([^\{]*?)};/strip_decoration($1, $2, $3)/ge;
        $line = preg_replace_callback(<<<REGEXP
/&(\w+)\(([^()]+?)\)\{([^{]*?)\}[;]{0,1}/ui
REGEXP
            /*        $line = preg_replace_callback(<<<REGEXP
            / \&(\w +)\(([^\(\)]+?)\){
                            ([^\{]*?)};/ui
            REGEXP*/
            , 'strip_decoration', $line);
//        $line = ~s / \&(\w +)\(([^\(\)]+?)\){
//                    ([^\{]*?)};/strip_decoration($1, $2, $3)/ge;
        $line = preg_replace_callback(<<<REGEXP
/&(\w+)\(([^()]+?)\)\{([^{]*?)\}[;]{0,1}/ui
REGEXP
            /*        $line = preg_replace_callback(<<<REGEXP
            / &(\w +)\(([^\(\)]+?)\){
                            ([^\{]*?)};/ui
            REGEXP*/
            , 'strip_decoration', $line);

        # 改行置換
//        $line = ~s / ~$/\\\\ /;
        if (!empty($line)) {
            $line.='\\\\ ';

        }
        $line = decoration($line);


        # heading
//        $line = ~s /^\*\s * ([^\*].*?)\[#.*$/heading(6, $1)/e;
        $line = preg_replace_callback(/** @lang RegExp */
//            "/^\*\s([^\*].*?)\[#.*$/ui",
            "/^\*\s{0,1}([^\*].*?)((\[#.*$)|(\s*$))/ui",
            function ($maches) {
//                var_dump(mb_convert_variables('sjis-win','utf-8',$maches));
//                var_dump($maches);
//                var_dump( heading(6, $maches[1]),'sjis-win');
//                exit;
                return heading(6, $maches[1]);
            }, $line);

        /*        $line = ~s /^\*{
                                2}\s * ([^\*].*?)\[#.*$/heading(5, $1)/e;*/
        $line = preg_replace_callback(/** @lang RegExp */
//            "/^\*{2}\s([^\*].*?)\[#.*$/ui",
            "/^\*{2}\s{0,1}([^\*].*?)((\[#.*$)|(\s*$))/ui",
            function ($maches) {
                return heading(5, $maches[1]);
            }, $line);
        /*        $line = ~s /^\*{
                                3}\s * ([^\*].*?)\[#.*$/heading(4, $1)/e;*/
        $line = preg_replace_callback(/** @lang RegExp */
//            "/^\*{3}\s([^\*].*?)\[#.*$/ui",
            "/^\*{3}\s{0,1}([^\*].*?)((\[#.*$)|(\s*$))/ui",
            function ($maches) {
                return heading(4, $maches[1]);
            }, $line);
        /*        $line = ~s /^\*{
                                4}\s * ([^\*].*?)\[#.*$/heading(3, $1)/e;*/
        $line = preg_replace_callback(/** @lang RegExp */
//            "/^\*{4}\s([^\*].*?)\[#.*$/ui",
            "/^\*{4}\s{0,1}([^\*].*?)((\[#.*$)|(\s*$))/ui",
            function ($maches) {
                return heading(3, $maches[1]);
            }, $line);
        /*        $line = ~s /^\*{
                                5}\s * ([^\*].*?)\[#.*$/heading(2, $1)/e;*/
        $line = preg_replace_callback(/** @lang RegExp */
//            "/^\*{5}\s([^\*].*?)\[#.*$/ui",
            "/^\*{5}\s{0,1}([^\*].*?)((\[#.*$)|(\s*$))/ui",
            function ($maches) {
                return heading(2, $maches[1]);
            }, $line);

        # list
//        $line = ~s /^(\++)\s * ([^\-]*.*)$/convert_ol($1, $2)/e;
        $line = preg_replace_callback(/** @lang RegExp */
            "/^(\++)\s{0,1}([^\-]*.*)$/ui",
            function ($matches) {
                return convert_ol($matches[1], $matches[2] ?? '');
            }, $line);


//        $line = ~s /^(\- +)\s * ([^\-]*.*)$/convert_ul($1, $2)/e;
        $line = preg_replace_callback(/** @lang RegExp */
            "/^(\-+)\s{0,1}([^\-]*.*)$/ui",
            function ($matches) {
                return convert_ul($matches[1], $matches[2] ?? '');
            }, $line);

        # smile
//        $line = ~s / \&(\w +);/smile($1)/ge;
        $line = preg_replace_callback(/** @lang RegExp */
            "/ \&(\w+);/ui",
            function ($matches) {
                return smile($matches[1]);
            }, $line);

        # table
//        if ($line = ~ /^\| /) {
        if (preg_match(/** @lang RegExp */
            "/^\|/ui", $line)) {
            $line = convert_table($line);
//            var_dump($line);
        } else {
            # TODO
            # reset format
        }

        # table は直前の行が空行じゃないとダメっぽい
//        if (scalar(@doku_lines)) {
//        $doku_lines = implode('',$doku_lines);
        if (!empty($doku_lines)) {
            if (
//                $line = ~ /^[\^\|]/
                //今の行がtable
                preg_match(/** @lang RegExp */
                    "/^[|\^]/ui", $line)
//             && $doku_lines[-1] !~ /^[\^\|]/
                //直前の行がtableでない
                && preg_match(/** @lang RegExp */
                    "/^[^|\^]/ui", end($doku_lines))
//            && $doku_lines[-1] ne "")
                && end($doku_lines) != ""
            ) {
//                push @doku_lines, "\n";
                array_push($doku_lines, "\r\n");
            }
        }

        # link (中に|を含むので table より後に処理)
//        $line = ~s / \[\[(.+?)\]\] / convert_link($1, $in_subdir)/ge;
        $line = preg_replace_callback(/** @lang RegExp */
            "/ \[\[(.+?)\]\] /ui", function ($matches) use ($in_subdir) {
            return convert_link($matches[1], $in_subdir);
        }, $line);

        # email link (mailto)
//        $line = ~s / (^|[^\[])([a - zA - Z0 - 9\._\-]+\@[a - zA - Z0 - 9\.]+\.[a - zA - Z0 - 9] +)([^\]]|$)/$1\[\[$2\]\]$3 / g;
        $line = preg_replace(/** @lang RegExp */
            "/ (^|[^\[])([a - zA - Z0 - 9._\-]+@[a - zA - Z0 - 9.]+\.[a - zA - Z0 - 9] +)([^\]]|$)/ui",
            /** @lang RegExp */
            "$1\[\[$2\]\]$3", $line);

//        $line = ~s / \&nbsp;/\x20 / g;
        $line = preg_replace(/** @lang RegExp */
            "/ &nbsp;/ui", "\x20 ", $line);

//        if ($line = ~ /\\\\$/) {
        if (preg_match("/\\\\$/ui", $line)) {
//            push @doku_lines, $line . " ";
            array_push($doku_lines, $line . " ");
        } else {
//            push @doku_lines, $line . "\n";
            array_push($doku_lines, $line . "\r\n");
        }
    }

//    push @doku_lines, "</code>\n" if ($pre) ;
    if ($pre) {
        array_push($doku_lines, "</code>\r\n");
    }

//    $r->close;
    fclose($r);

//    my $w = new IO::File $doku_file, "w";
    $w = fopen($doku_file, 'w');

//    if (!defined $w) {
    if ($w === false) {
//    warn "can't open $doku_file: $!";
        echo "can't open " . $doku_file . PHP_EOL;

        return false;
    }
//    foreach my $line (@doku_lines){
    foreach ($doku_lines as $line) {
//        print $w encode("utf-8", $line);
        fwrite($w, $line);
    }
//    $w->close;
    fclose($w);

    # copy last modified
//    system("/bin/touch", "-r", $src_file, $doku_file);
    touch($doku_file, $fileModified);

    return true;
}

/**
 * @param $line
 * @return string|string[]|null
 */
function decoration($line)
{
    if (empty($line)) {
        return '';
    }
    var_dump('decoration!');
    var_dump($line);
//        $line = ~s / \&br;/\\\\ / g;
    $line = preg_replace(/** @lang RegExp */
        "/\&br;/ui", "\\\\ ", $line);

    # italicBold
    $line = preg_replace(/** @lang RegExp */
        "#'''''(.+?)'''''#ui", " // ** $1 ** // ", $line);

    # italic
//        $line = ~s#'''(.+?)'''#//$1//#g;
    $line = preg_replace(/** @lang RegExp */
        "#'''(.+?)'''#ui", " //$1// ", $line);

    # bold
//        $line = ~s / ''(.+?)'' / \*\*$1\*\* / g;
    $line = preg_replace(/** @lang RegExp */
//            "/[^']''(.+?)''[^']/ui", " **$1** ", $line);
        "/''(.+?)''/ui", " **$1** ", $line);

    #code
    $line = preg_replace(/** @lang RegExp */
        "#@@@(.+)@@@#ui", "<code>$1</code>", $line);

    # del
//        $line = ~s#\%\%(.+?)\%\%#<del>$1</del>#g;
    $line = preg_replace(/** @lang RegExp */
        "#%%(.+?)%%#ui", "<del>$1</del>", $line);
    $line = preg_replace(/** @lang RegExp */
        "#___(.+?)___#ui", "<del>$1</del>", $line);
    $line = preg_replace(/** @lang RegExp */
        "#@@(.+?)@@#ui", "<del>$1</del>", $line);


    # escape
//        $line = ~s#(?:^|[^:])(//)#%%$1%%#g;
    $line = preg_replace(/** @lang RegExp */
        "#(?:^|[^:])([^\s]//[^\s])#ui", "%%$1%%", $line);

    return $line;
}

function get_last_key($array)
{
    end($array);

    return key($array);
}

//sub convert_file {
//    my ($src_file) = @_;
//
//    my $in_subdir = 0;
//    my $last_line = "";
//
//    my $r = new IO::File $src_file, "r";
//
//    my $dokuwiki_filename = convert_filename($src_file);
//    if ($dokuwiki_filename =~ /\//) {
//        $in_subdir = 1;
//    }
//
//    # 小文字にしたり、記号を変換してないページ名
//    my $pagename = decode($input_encoding, pukiwiki_filename_decode($src_file));
//
//    return if ($pagename =~ /^:/); # 特殊ファイル
//
//    $pagename =~ s/\.txt//;
//    $pagename =~ s/\//:/g; # namespace の区切りは / ではなく :
//
//    my $doku_file = sprintf "%s/%s",
//                            $dst_dir,
//                            $dokuwiki_filename;
//
//    # 既に存在していたら上書きしない
//    if ($dont_overwrite && -f $doku_file) {
//        print "SKIP " . encode("utf-8", $doku_file),"\n" if ($verbose);
//        return;
//    }
//
//    my $doku_file_dir = dirname($doku_file);
//    if (! -d $doku_file_dir) {
//        mkpath($doku_file_dir);
//    }
//
//    my $pre = 0;
//    my $prettify = 0;
//    my @sp_buf = (); # #contents
//
//    my @doku_lines = ();
//
//    # 見出しを追加。これはDokuWikiでuseheadingオプションを使う場合に有効
//    if ($use_heading) {
//        my $pageid = $pagename;
//        $pageid =~ tr/[A-ZＡ-Ｚ]/[a-zａ-ｚ]/; # pageidは小文字
//        push @doku_lines, "====== " . $pageid . " ======\n\n";
//    }
//
//    while (my $line = <$r>) {
//        $line = decode($input_encoding, $line);
//        $line =~ s/[\r\n]+$//;
//
//        # ----
//        # #contents
//        if ($line eq "----" && scalar(@sp_buf) == 0) {
//            push @sp_buf, $line;
//            next;
//        }
//        elsif ($line eq "----" && scalar(@sp_buf) == 2) {
//            @sp_buf = ();
//            next;
//        }
//        elsif ($line eq "#contents" && scalar(@sp_buf) == 1) {
//            push @sp_buf, $line;
//            next;
//        }
//        else {
//            foreach (@sp_buf) {
//                push @doku_lines, $_ . "\n";
//            }
//            @sp_buf = ();
//        }
//        # ----
//
//
//        if ($use_indexmenu_plugin) {
//            $line =~ s/^#ls2?\((.*)\).*$/convert_ls_indexmenu($pagename, $1)/e;
//            $line =~ s/^#ls2?$/convert_ls_indexmenu($pagename)/e;
//        }
//
//        # prettify etention
//        if ($line =~ /^#prettify{{/) {
//            push @doku_lines, "<code>\n" if (! $pre);
//            $prettify = 1;
//            next;
//        }
//        elsif ($prettify) {
//            if ($line =~ /^\}\}/) {
//                push @doku_lines, "</code>\n";
//                $prettify = 0;
//            }
//            else {
//                push @doku_lines, $line . "\n";
//            }
//            next;
//        }
//
//        if ($line =~ s/^\x20// || $line =~ /^\t/) {
//            if (! $pre) {
//                if (scalar(@doku_lines) && $doku_lines[-1] =~ /^\s+\-/) {
//                    $doku_lines[-1] =~ s/\n$//;
//                }
//                push @doku_lines, "<code>\n";
//            }
//            push @doku_lines, $line . "\n";
//            $pre = 1;
//            next;
//        }
//        elsif ($pre) {
//            push @doku_lines, "</code>\n";
//            $pre = 0;
//        }
//
//        if ($line =~ /^\-+$/) {
//            push @doku_lines, $line . "\n";
//            next;
//        }
//
//        # ref
//        $line =~ s/\&ref\((.+?)\);/convert_ref($pagename, $1)/ge;
//        $line =~ s/#ref\((.+?)\)/convert_ref($pagename, $1)/ge;
//
//        next if ($line =~ /^#/ && $ignore_unknown_macro);
//
//        # definitions
//        $line =~ s/^:(.*?)\|(.*)$/  = $1 : $2/;
//
//        # 装飾を削る (2回なのは入れ子対応、3回やっとく？)
//        $line =~ s/\&(\w+)\(([^\(\)]+?)\){([^\{]*?)};/strip_decoration($1, $2, $3)/ge;
//        $line =~ s/\&(\w+)\(([^\(\)]+?)\){([^\{]*?)};/strip_decoration($1, $2, $3)/ge;
//
//        # 改行置換
//        $line =~ s/~$/\\\\/;
//        $line =~ s/\&br;/\\\\ /g;
//
//        # italic
//        $line =~ s#'''(.+?)'''#//$1//#g;
//
//        # bold
//        $line =~ s/''(.+?)''/\*\*$1\*\*/g;
//
//        # del
//        $line = ~s#\%\%(.+?)\%\%#<del>$1</del>#g;
//
//        # escape
//        $line = ~s#(?:^|[^:])(//)#%%$1%%#g;
//
//        # heading
//        $line = ~s /^\*\s * ([^\*].*?)\[#.*$/heading(6, $1)/e;
//        $line = ~s /^\*{
//        2}\s * ([^\*].*?)\[#.*$/heading(5, $1)/e;
//        $line = ~s /^\*{
//        3}\s * ([^\*].*?)\[#.*$/heading(4, $1)/e;
//        $line = ~s /^\*{
//        4}\s * ([^\*].*?)\[#.*$/heading(3, $1)/e;
//        $line = ~s /^\*{
//        5}\s * ([^\*].*?)\[#.*$/heading(2, $1)/e;
//
//        # list
//        $line = ~s /^(\++)\s * ([^\-]*.*)$/convert_ol($1, $2)/e;
//        $line = ~s /^(\- +)\s * ([^\-]*.*)$/convert_ul($1, $2)/e;
//
//        # smile
//        $line = ~s / \&(\w +);/smile($1)/ge;
//
//        # table
//        if ($line = ~ /^\| /) {
//            $line = convert_table($line);
//        }
//        else {
//            # TODO
//            # reset format
//        }
//
//        # table は直前の行が空行じゃないとダメっぽい
//        if (scalar(@doku_lines)) {
//            if ($line = ~ /^[\^\|]/
//             && $doku_lines[-1] !~ /^[\^\|]/ && $doku_lines[-1] ne "") {
//                push @doku_lines, "\n";
//            }
//        }
//
//        # link (中に|を含むので table より後に処理)
//        $line = ~s / \[\[(.+?)\]\] / convert_link($1, $in_subdir)/ge;
//
//        # email link (mailto)
//        $line = ~s / (^|[^\[])([a - zA - Z0 - 9\._\-]+\@[a - zA - Z0 - 9\.]+\.[a - zA - Z0 - 9] +)([^\]]|$)/$1\[\[$2\]\]$3 / g;
//
//        $line = ~s / \&nbsp;/\x20 / g;
//
//        if ($line = ~ /\\\\$/) {
//        push @doku_lines, $line . " ";
//        }
//        else {
//            push @doku_lines, $line . "\n";
//        }
//    }
//
//    push @doku_lines, "</code>\n" if ($pre) ;
//
//    $r->close;
//
//    my $w = new IO::File $doku_file, "w";
//    if (!defined $w) {
//                warn "can't open $doku_file: $!";
//        return;
//    }
//    foreach my $line (@doku_lines){
//    print $w encode("utf-8", $line);
//    }
//    $w->close;
//
//    # copy last modified
//    system("/bin/touch", "-r", $src_file, $doku_file);
//}

/**
 * @param int $n
 * @param string $str
 * @return string
 */
function heading($n = 1, $str = '')
{
//                my($n, $str) = @_;

//                if ($str = ~ /\[\[(.*)\]\] /) {
    if (preg_match("/\[\[(.*)\]\] /ui", $str, $matches)) {
//                    my $link = $1;
        $link = $matches[1];
//        $link = ~s /^.*[>\|]//;
        $link = preg_replace("/^.*[>\|]+/ui", '', $link);
        $str = $link;
    }
    //ヘッダタグ内ではboldが効かない
    $str = preg_replace(/** @lang RegExp */
        "#\*\*(.+?)\*\*#ui", "$1", $str);


//    return "=" x $n . " " . $str . " " . "=" x $n;
    return str_repeat("=", $n) . " " . $str . " " . str_repeat("=", $n) . "\r\n";
}

/*sub heading {
                my($n, $str) = @_;

                if ($str = ~ /\[\[(.*)\]\] /) {
                    my $link = $1;
        $link = ~s /^.*[>\|]//;
        $str = $link;
    }
    return "=" x $n . " " . $str . " " . "=" x $n;
}*/


/**
 * @param string $src_pagename
 * @param string $namespace
 * @return string
 */
function convert_ls_indexmenu($src_pagename = '', $namespace = '')
{
//                my($src_pagename, $namespace) = @_;

//                $namespace = "" if (!$namespace) ;
//    $namespace = ~s / \//:/g;
    $namespace = strtr($namespace, '/', ':');

//        $namespace = $src_pagename if (!$namespace) ;
    if (empty($namespace)) {
        $namespace = $src_pagename;
    }
//    $namespace = ~s / \x20 +/_ / g;
    $namespace = preg_replace("/ \x20 +/ui", "_ ", $namespace);

//    if ($namespace) {
    if (!empty($namespace)) {
        return "{{indexmenu>" . $namespace . "|tsort}}";
    } else {
        return "{{indexmenu>.|tsort}}";
    }
}

/*sub convert_ls_indexmenu {
                my($src_pagename, $namespace) = @_;

                $namespace = "" if (!$namespace) ;
    $namespace = ~s / \//:/g;
        $namespace = $src_pagename if (!$namespace) ;
    $namespace = ~s / \x20 +/_ / g;

    if ($namespace) {
        return "{{indexmenu>" . $namespace . "|tsort}}"
    } else {
        return "{{indexmenu>.|tsort}}"
    }
}*/

/**
 * @param string $line
 * @param $format
 * @return string
 */
function convert_table($line = '', $format = '')
{
//                my($line, $format) = @_;

    $is_header = 0;
    $is_format = 0;
    $is_footer = 0;

//    if ($line = ~s / \|h$/|/) {
    if (preg_match(/** @lang RegExp */
        "/\|h$/ui", $line)) {
        $line = preg_replace(/** @lang RegExp */
            "/\|h$/ui", '|', $line);
        $is_header = 1;
//        echo 'headder row matched :'.mb_convert_encoding($line,'sjis-win','utf-8').PHP_EOL;
    } elseif (preg_match(/** @lang RegExp */
        "/\|c$/ui", $line)) {
        $line = preg_replace(/** @lang RegExp */
            "/\|c$/ui", '|', $line);
        # TODO
        $is_format = 1;
//        return "";
    } //    elseif( $line = ~s / \|f$/|/) {
    elseif (preg_match(/** @lang RegExp */
        "/\|f$/ui", $line)) {
        $line = preg_replace(/** @lang RegExp */
            "/\|f$/ui", '|', $line);
        $is_footer = 1;
//        return "";
    }

//    my @cols = split(/\s * \|\s */, $line);
    $cols = preg_split(/** @lang RegExp */
        "/\s*\|\s*/ui", $line);
//    shift @cols;
    array_shift($cols);

    $new_line = "";

    $span = 0;

//    echo 'headder row flag:'.$is_header.PHP_EOL;

//    foreach my $col (@cols){
    foreach ($cols as $col) {

        $pos = "LEFT";

//        while ($col = ~s /^(LEFT | CENTER | RIGHT | COLOR\( .*?\) | BGCOLOR\( .*?\) | SIZE\( .*?\))://) {
        while (preg_match(/** @lang RegExp */
            "/^(LEFT|CENTER|RIGHT|JUSTIFY|COLOR\(.*?\)|BGCOLOR\(.*?\)|SIZE\(.*?\)):/ui", $col, $matches)) {
            $col = preg_replace(/** @lang RegExp */
                "/^(LEFT|CENTER|RIGHT|JUSTIFY|COLOR\(.*?\)|BGCOLOR\(.*?\)|SIZE\(.*?\)):/ui", '', $col);
//            if ($1 eq "LEFT" || $1 eq "CENTER" || $1 eq "RIGHT") {
            if ($matches[1] == "LEFT" || $matches[1] == "CENTER" || $matches[1] == "RIGHT" || $matches[1] == "JUSTIFY") {
//                    $pos = $1;
                $pos = $matches[1];
            }
//            $pos = "CENTER" if ($is_header) ;
            if ($is_header) {
                $pos = "CENTER";
            }
        }

        if ($span == 0) {
//            $new_line .= ($col = ~s /^~(?!\s * $)// || $is_header) ? '^' : '|';
            if (preg_match(/** @lang RegExp */
                    "/^~(?!\s*$)/ui", $col) || $is_header
            ) {
//                echo 'header column :'.mb_convert_encoding($col,'sjis-win','utf-8').PHP_EOL;

                $col = preg_replace(/** @lang RegExp */
                    "/^~(?!\s*$)/ui", '', $col);
                $new_line .= '^';

            } else {
                $new_line .= '|';
            }

        }

//        if ($col eq ">") {
        if ($col == ">") {
            ++$span;
//                    next;
            continue;
        } //        elsif($col = ~ /^\s * ~\s * $/){
        elseif (preg_match(/** @lang RegExp */
            "/^\s*~\s*$/ui", $col)) {
            $col = " ::: ";
        } //        elsif($col eq "") {
        elseif ($col == "") {
            $col = " ";
        }

//        if ($pos eq "LEFT") {
        if ($pos == "LEFT") {
            $col .= "  ";
        } //        elsif($pos eq "CENTER") {
        elseif ($pos == "CENTER") {
            $col = "  " . $col . "  ";
        } //        elsif($pos eq "RIGHT") {
        elseif ($pos == "RIGHT") {
            $col = "  " . $col;
        } elseif ($pos == "JUSTIFY") {
            $col = trim($col);
        }

        $new_line .= $col;
//        if ($col ne "" && $span) {
        if ($col != "" && $span) {
//                    $new_line .= "|" x $span;
            $new_line .= str_repeat("|", $span);
            $span = 0;
        }
    }
    $new_line .= ($is_header) ? '^' : '|';

    return $new_line;
}

//sub convert_table {
//                my($line, $format) = @_;
//
//                my $is_header = 0;
//    my $is_format = 0;
//    my $is_footer = 0;
//
//    if ($line = ~s / \|h$/|/) {
//                    $is_header = 1;
//                }
//    elsif($line = ~s / \|c$/|/) {
//                    # TODO
//                    $is_format = 1;
//                    return "";
//                }
//    elsif($line = ~s / \|f$/|/) {
//                    $is_footer = 1;
//                    return "";
//                }
//
//    my @cols = split(/\s * \|\s */, $line);
//    shift @cols;
//
//    my $new_line = "";
//
//    my $span = 0;
//
//    foreach my $col (@cols){
//    my $pos = "";
//
//        while ($col = ~s /^(LEFT | CENTER | RIGHT | COLOR\( .*?\) | BGCOLOR\( .*?\) | SIZE\( .*?\))://) {
//            if ($1 eq "LEFT" || $1 eq "CENTER" || $1 eq "RIGHT") {
//    $pos = $1;
//            }
//            $pos = "CENTER" if ($is_header) ;
//        }
//
//        if ($span == 0) {
//            $new_line .= ($col = ~s /^~(?!\s * $)// || $is_header) ? '^' : '|';
//        }
//
//        if ($col eq ">") {
//    ++$span;
//    next;
//}
//        elsif($col = ~ /^\s * ~\s * $/){
//        $col = " ::: ";
//        }
//        elsif($col eq "") {
//    $col = " ";
//}
//
//        if ($pos eq "LEFT") {
//    $col .= "  ";
//}
//        elsif($pos eq "CENTER") {
//    $col = "  " . $col . "  ";
//}
//        elsif($pos eq "RIGHT") {
//    $col = "  " . $col;
//}
//
//        $new_line .= $col;
//        if ($col ne "" && $span) {
//    $new_line .= "|" x $span;
//            $span = 0;
//        }
//    }
//    $new_line .= ($is_header) ? '^' : '|';
//
//    return $new_line;
//}

/**
 * @param string $str
 * @return string
 */
function smile($str = '')
{
//    my($str) = @_;
    global $smiles;

//    if (exists $smiles{$str}) {
    if (isset($smiles[$str])) {
//        return $smiles{$str};
        return $smiles[$str];
    } else {
//        return sprintf '&%s;', $str;
        return sprintf('&%s;', $str);
    }
}

/*sub smile {
    my($str) = @_;

    if (exists $smiles{$str}) {
        return $smiles{$str};
    }
    else {
        return sprintf '&%s;', $str;
    }
}*/

/**
 * @param string $mark
 * @param string $str
 * @return string
 */
function convert_ol($mark = '', $str = '')
{
//    my($mark, $str) = @_;

//    my $space = "  " x length($mark);
    $space = str_repeat("  ", strlen($mark));

    return $space . "- " . $str;
}

/*sub convert_ol {
    my($mark, $str) = @_;

    my $space = "  " x length($mark);

    return $space . "- " . $str;
}*/

/**
 * @param string $mark
 * @param string $str
 * @return string
 */
function convert_ul($mark = '', $str = '')
{
//    my($mark, $str) = @_;

//    my $space = "  " x length($mark);
    $space = str_repeat("  ", strlen($mark));

    return $space . "* " . $str;
}

/*sub convert_ul {
    my($mark, $str) = @_;

    my $space = "  " x length($mark);

    return $space . "* " . $str;
}*/

/**
 * @param string $str
 * @param $in_subdir
 * @return string
 */
function convert_link($str = '', $in_subdir)
{
//    my($str, $in_subdir) = @_;

    $text = '';
    $url = '';

    # [[text>url]]
//    if ($str = ~ />/) {
    if (preg_match("/>/ui", $str)) {
//        ($text, $url) = split(/>/, $str, 2);
        list($text, $url) = explode('>', $str, 2);
//        $url = ~s / \//:/g if ($url !~ /^http/);
        if (preg_match("/^http/ui", $url)) {
            $url = preg_replace(/** @lang RegExp */
                "/ \//ui", ':', $url);
        }
    }

    # [[text:url]]
//    elsif($str !~ /^http / && $str = ~ /:/) {
    elseif (
        preg_match("/^http /ui", $str)
        && preg_match("/:/ui", $str)
    ) {
//        ($text, $url) = split(/:/, $str, 2);
        list($text, $url) = explode(':', $str, 2);
//        $url = ~s / \//:/g if ($url !~ /^http/);
        if (preg_match("/^http/ui", $url)) {
            $url = preg_replace(/** @lang RegExp */
                "/ \//ui", ':', $url);
        }

    }

    # [[Internal/Name]]
//    elsif($str !~ /^http / && $str = ~ /\//) {
    elseif (
        preg_match(/** @lang RegExp */
            "/^http/ui", $str)
        && preg_match(/** @lang RegExp */
            "/\//ui", $str)
    ) {
//        $str = ~s / \//:/g;
        $str = preg_replace(/** @lang RegExp */
            "/ \//ui", ':', $str);
    } # [[WikiName]], [[http://....]]
    else {
//    $url = $str if ($str = ~ /^http /);
        if (preg_match(/** @lang RegExp */
            "/^http/ui", $str)) {
            $url = $str;
        }
//        $str = "start" if ($str eq "FrontPage");
        if ($str == "FrontPage") {
            $str = "start";
        }
    }

//    if (!$url) {
    if (empty($url)) {
        if ($in_subdir) {
            return "[[:" . $str . "]]";
        } else {
            return "[[" . $str . "]]";
        }
    } //    elsif($url && !$text){
    elseif (!empty($url) && empty($text)) {
        return "[[" . $url . "]]";
    } else {
//    return "[[" . join("|", $url, $text) . "]]";
        return "[[" . implode("|", [$url, $text]) . "]]";
    }
}

//sub convert_link {
//    my($str, $in_subdir) = @_;
//
//    my $text;
//    my $url;
//
//    # [[text>url]]
//    if ($str = ~ />/) {
//        ($text, $url) = split(/>/, $str, 2);
//        $url = ~s / \//:/g if ($url !~ /^http/);
//            }
//
//    # [[text:url]]
//    elsif($str !~ /^http / && $str = ~ /:/) {
//        ($text, $url) = split(/:/, $str, 2);
//        $url = ~s / \//:/g if ($url !~ /^http/);
//            }
//
//    # [[Internal/Name]]
//    elsif($str !~ /^http / && $str = ~ /\//) {
//        $str = ~s / \//:/g;
//        }
//
//    # [[WikiName]], [[http://....]]
//    else {
//    $url = $str if ($str = ~ /^http /);
//        $str = "start" if ($str eq "FrontPage");
//    }
//
//    if (!$url) {
//        if ($in_subdir) {
//            return "[[:" . $str . "]]";
//        } else {
//            return "[[" . $str . "]]";
//        }
//    }
//    elsif($url && !$text){
//        return "[[" . $url . "]]";
//    }
//    else {
//    return "[[" . join("|", $url, $text) . "]]";
//}
//}


function convert_ref($src_pagename = '', $str = '')
{
//    my($src_pagename, $str) = @_;

//    my($link_to, $option) = split(/,/, $str, 2);
    list($link_to, $option) = explode(',', $str, 2);

//    if ($link_to = ~ /^http /) {
    if (preg_match("/^http /ui", $link_to)) {
//        return sprintf "[[%s|{{%s}}]]", $link_to, $link_to;
        return sprintf("[[%s|{{%s}}]]", $link_to, $link_to);
    } else {
//        return sprintf "{{%s:%s}}", $src_pagename, $link_to;
        return sprintf("{{%s:%s}}", $src_pagename, $link_to);
    }
}

//sub convert_ref {
//    my($src_pagename, $str) = @_;
//
//    my($link_to, $option) = split(/,/, $str, 2);
//
//    if ($link_to = ~ /^http /) {
//        return sprintf "[[%s|{{%s}}]]", $link_to, $link_to;
//    }
//    else {
//        return sprintf "{{%s:%s}}", $src_pagename, $link_to;
//    }
//}

/**
 * @param string $filename
 * @return mixed
 */
function convert_filename($filename = '')
{
//    my($filename) = @_;
    global $input_encoding, $verbose, $KIGO_STR;

//    my $decoded = decode($input_encoding, pukiwiki_filename_decode($filename));


    $decoded = mb_convert_encoding(pukiwiki_filename_decode($filename), 'utf-8', $input_encoding);

//    print encode("utf-8", $decoded),"\n" if ($verbose) ;
    if ($verbose) {
        echo $decoded . PHP_EOL;
    }

    # マルチバイト => ascii の正規化 結果 _ になるので _ に置換
//    $decoded = ~s / [$KIGO_STR] +/_ / g;
    $decoded = preg_replace("/ [" . $KIGO_STR . "] +/ui", "_ ", $decoded);

    # 半角記号のうち .-/ 以外を _ に置換(連続するものは1つにまとめる)
//    $decoded = ~s / [\x20 - \x2c\x3a - \x40\x5b - \x60\x7b - \x7e] +/_ / g;
    $decoded = preg_replace("/ [\x20 - \x2c\x3a - \x40\x5b - \x60\x7b - \x7e] +/ui", "_ ", $decoded);

    # 末尾の _ は削る
//    $decoded = ~s / [_\.\-]+.txt$/.txt /;
    $decoded = preg_replace(/** @lang RegExp */
        "/[\.\-]+.txt$/ui", ".txt", $decoded);
    # ディレクトリの末尾からも削る
//    $decoded = ~s#[_\.\-]+/#/#g;
    $decoded = preg_replace(/** @lang RegExp */
        "#[_\.\-]+/#ui", '/', $decoded);
    # 先頭からも削る
//    $decoded = ~s#/[_\.\-]+#/#g;
    $decoded = preg_replace("#/[_\.\-]+#ui", '/', $decoded);

    # アルファベットは小文字に置換(全角も)
//    $decoded = ~tr / [A - ZＡ - Ｚ] / [a - zａ - ｚ] /;
    $decoded = mb_convert_kana($decoded, 'asKV');
    $decoded = strtolower($decoded);

    # .-/a-z 以外を url encode
    $dokuwiki_name = dokuwiki_url_encode($decoded);

    var_dump($decoded);

//    return encode("utf-8", $dokuwiki_name);
    return $dokuwiki_name;
}

//sub convert_filename {
//    my($filename) = @_;
//
//    my $decoded = decode($input_encoding, pukiwiki_filename_decode($filename));
//
//    print encode("utf-8", $decoded),"\n" if ($verbose) ;
//
//
//    # マルチバイト => ascii の正規化 結果 _ になるので _ に置換
//    $decoded = ~s / [$KIGO_STR] +/_ / g;
//
//    # 半角記号のうち .-/ 以外を _ に置換(連続するものは1つにまとめる)
//    $decoded = ~s / [\x20 - \x2c\x3a - \x40\x5b - \x60\x7b - \x7e] +/_ / g;
//
//    # 末尾の _ は削る
//    $decoded = ~s / [_\.\-]+.txt$/.txt /;
//    # ディレクトリの末尾からも削る
//    $decoded = ~s#[_\.\-]+/#/#g;
//    # 先頭からも削る
//    $decoded = ~s#/[_\.\-]+#/#g;
//
//    # アルファベットは小文字に置換(全角も)
//    $decoded = ~tr / [A - ZＡ - Ｚ] / [a - zａ - ｚ] /;
//
//    # .-/a-z 以外を url encode
//    my $dokuwiki_name = dokuwiki_url_encode($decoded);
//
//    return encode("utf-8", $dokuwiki_name);
//}

/*sub pukiwiki_filename_decode {
    my ($str) = @_;

    $str =~ s/([0-9A-F]{2})/pack("C",hex($1))/ge;

    if ($str eq "FrontPage.txt") {
        $str = "start.txt";
    }
    elsif ($str eq "MenuBar.txt") {
        $str = "sidebar.txt";
    }

    return $str;
}*/

function dokuwiki_url_encode($str = '')
{
//    my($str) = @_;
//    $str = encode("utf-8", $str);
    $str = mb_convert_encoding($str, "utf-8");
//    $str = ~s / ([^a - zA - Z0 - 9_ . \-\/])/uc sprintf("%%%02x", ord($1))/eg;
    $str = preg_replace_callback(/** @lang RegExp */
        "/ ([^a-zA-Z0-9_.\-\/]+)/ui", function ($matches) {
//        sprintf("%%%02x", ord($1))
        var_dump($matches);
        sprintf("%%%02x", ord($matches[1]));
    }, $str);

//    return decode("utf-8", $str);
    return $str;
}

//sub dokuwiki_url_encode {
//    my($str) = @_;
//    $str = encode("utf-8", $str);
//    $str = ~s / ([^a - zA - Z0 - 9_ . \-\/])/uc sprintf("%%%02x", ord($1))/eg;
//    return decode("utf-8", $str);
//}

function strip_decoration($matches = [])
{
//    my($type, $attr, $str) = @_;
    global $use_font_size_plugin, $use_font_color_plugin;
    $type = strtolower($matches[1]) ?? "";
    $attr = $matches[2] ?? '';
    $str = $matches[3] ?? '';


//    if ($type eq "size" && $use_font_size_plugin) {
    if ($type == "size" && $use_font_size_plugin && !empty($attr)) {
        /*        $tmp = $matches;
                mb_convert_variables('sjis-win', 'utf-8', $tmp);
                var_dump($tmp);*/
        return sprintf('<fs %spx>%s</fs>', $attr, $str);

        /*        if ($attr > 20) {
//            return sprintf qq(####%s####), $str;
            return sprintf('####%s####', $str);
        } else {
//            return sprintf qq(##%s##), $str;
            return sprintf('##%s##', $str);
        }*/
    } //    elsif($type eq "color" && $use_font_color_plugin) {
    elseif ($type == "color" && $use_font_color_plugin && !empty($attr)) {
//        return sprintf qq(<color % s / white>%s </color >), $attr, $str;
//        return sprintf('<color %s/white>%s</color>', $attr, $str);
        $attr = strtr($attr, ',', '/');

        return sprintf('<color %s>%s</color>', $attr, $str);
    } else {
        return $str;
    }
}
//sub strip_decoration {
//    my($type, $attr, $str) = @_;
//
//    if ($type eq "size" && $use_font_size_plugin) {
//        if ($attr > 20) {
//            return sprintf qq(####%s####), $str;
//        } else {
//            return sprintf qq(##%s##), $str;
//        }
//    }
//    elsif($type eq "color" && $use_font_color_plugin) {
//        return sprintf qq(<color % s / white>%s </color >), $attr, $str;
//    }
//    else {
//        return $str;
//    }
//}

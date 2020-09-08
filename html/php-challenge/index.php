<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
	// ログインしている
	$_SESSION['time'] = time();

	$members = $db->prepare('SELECT * FROM members WHERE id=?');
	$members->execute(array($_SESSION['id']));
	$member = $members->fetch();
} else {
	// ログインしていない
	header('Location: login.php'); exit();
}

// 投稿を記録する
if (!empty($_POST)) {
	if ($_POST['message'] != '') {
		$message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?,retweet_post_id=?, created=NOW()');
		$message->execute(array(
			$member['id'],
			$_POST['message'],
			$_POST['reply_post_id'],
			$_POST['retweet_post_id']
		));

		header('Location: index.php'); exit();
	}
}

// 投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
	$page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// 返信の場合
if (isset($_REQUEST['res'])) {
	$response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
	$response->execute(array($_REQUEST['res']));

	$table = $response->fetch();
	$message = '@' . $table['name'] . ' ' . $table['message'];
}

//RTボタンの処理★
if (isset($_REQUEST['rt'])) { 
	$retweet  = $db->prepare('select id, message , member_id, retweet_post_id, retweet_member_id from posts where id=? order by created desc '); 
	$retweet->execute(array($_REQUEST['rt']));
	$rt_msg = $retweet->fetch();
	$rt_counts = $db->prepare('SELECT  count(*) as rt_cnt from posts where retweet_post_id =? and retweet_member_id = ? '); 
		//元投稿をRT
		if ((int)$rt_msg['retweet_post_id'] === 0) {

			$rt_counts->execute(array(
				$rt_msg['id'],
				$member['id']
			));
			$rt_count = $rt_counts->fetch();
			//RTをRT
		} elseif ((int)$rt_msg['retweet_post_id'] !== 0) {
	
			$rt_counts->execute(array(
				$rt_msg['retweet_post_id'],
				$member['id']
			));
			$rt_count = $rt_counts->fetch();
    }
    //リレーション
    $rt_datas = $db->prepare('SELECT a.id, a.message,a.member_id, b.id, b.retweet_post_id, b.retweet_member_id FROM posts a left join posts b on a.id = b.retweet_post_id where a.message = b.message and a.id = ? ');
    if((int)$rt_msg['retweet_post_id'] === 0){
        echo '内部で結合';
        $rt_datas->execute(array(
            $rt_msg['id']
        ));
        $rt_data = $rt_datas ->fetch();
    }
    //そのユーザが初めてRT
	if ((int)$rt_count['rt_cnt'] === 0) { 
        //RTをDBに挿入
		$sent_rt = $db->prepare('INSERT INTO posts SET message=?, member_id =?, reply_post_id=0, retweet_post_id=?,      retweet_member_id=?, created=now() ');
        //大元RTする
		if ((int)$rt_msg['retweet_post_id'] === 0) { 
            
            $sent_rt->execute(array(
                $rt_msg['message'],
                $rt_msg['member_id'],
                $rt_msg['id'],
                $member['id']
            ));
            
            //既にRTされた投稿
		} elseif ((int)$rt_msg['retweet_post_id'] !== 0) { 
            $sent_rt->execute(array(
                $rt_msg['message'],
                $rt_msg['member_id'],
                $rt_msg['retweet_post_id'],
                $member['id']
            ));
            
		}
        header('Location:index.php?='.$rt_msg['retweet_post_id']);
        exit();
	}
    //削除	
    //既にそのユーザーがRTした投稿データ
	elseif ((int)$rt_count['rt_cnt'] >= 1) { 
        if ((int)$rt_msg['retweet_post_id'] === 0) {
            
            $delete = $db->prepare('delete from posts where id=? and member_id=?');
            $delete->execute(array(
                $rt_data['b.id'],
                $member['id']
            ));
            echo '元投稿';
            var_dump($rt_data['b.id']);
            var_dump($delete);
            
        }
        elseif ((int)$rt_msg['retweet_post_id'] !== 0) {
            
            $delete = $db->prepare('delete from posts  retweet_post_id = ? and retweet_member_id = ?'); 
            $delete->execute(array(
                $rt_msg['retweet_post_id'],
                $member['id']
            ));
            echo 'RT';
            var_dump($rt_msg['retweet_post_id']);
            var_dump($delete);

        }

    header('Location:index.php?='.$rt_msg['retweet_post_id']);
    exit();
    }
}
//いいね♡
if (isset($_REQUEST['like'])) { 
    //必要情報抽出
    $like_needs = $db -> prepare('select id , message, reply_post_id, retweet_post_id, retweet_member_id from posts where id=? order by created desc');
    $like_needs->execute(array( $_REQUEST['like']));
	$like_need = $like_needs->fetch();
	    //likesとposts 紐付け
		$like_rerations  = $db->prepare('select l.post_id,l.member_id, p.message, p.reply_post_id, p. retweet_post_id from likes l join posts p on l.post_id = p.id where p.id =? order by created desc '); 
		$like_rerations-> execute(array($_REQUEST['like']));
    $like_reration= $like_rerations->fetch();   
    //likesテーブルからカウント
    $like_counts = $db->prepare('SELECT  count(post_id) as cnt from likes where post_id = ? and member_id=?');
    //大元投稿
    if((int)$like_need['retweet_post_id'] === 0){
    $like_counts -> execute(array(
        $like_need['id'],
        $member['id']
        ));
        $like_count= $like_counts->fetch(); 
        //RTされた投稿
     }elseif((int)$like_need['retweet_post_id'] !== 0){
        $like_counts -> execute(array(
        $like_need['retweet_post_id'],
        $member['id']
    ));
    $like_count= $like_counts->fetch();
	}
	    //もしまだいいねされてなければデータ挿入
		if((int)$like_count['cnt'] === 0){
			$likes_sent = $db -> prepare('insert into likes set post_id=?, member_id=? ');
			//大元いいね
			if((int)$like_need['retweet_post_id'] === 0){
				$likes_sent->execute(array(
					$like_need['id'],
					$member['id']
				));
				$like_sent = $likes_sent->fetch();           
			//RTなら元投稿にいいね
			}elseif((int)$like_need['retweet_post_id'] !== 0){
				$likes_sent->execute(array(
					$like_need['retweet_post_id'],
					$member['id']
				));
				$like_sent = $likes_sent->fetch();          
			}
			//もし既にそのユーザーからいいねあれば削除
		}elseif((int)$like_count['cnt'] >= 0){
			$delete_likes= $db -> prepare('delete from likes where post_id=? and member_id=?');
			//大元投稿
			if((int)$like_need['retweet_post_id'] === 0){
				$delete_likes->execute(array(
					$like_need['id'],
					$member['id']
				));
				$delete_like = $delete_likes->fetch();
				//RTいいね
			}elseif((int)$like_need['retweet_post_id'] !== 0){
				$delete_likes->execute(array(
					$like_need['retweet_post_id'],
					$member['id']
				));
				$delete_like = $delete_likes->fetch();
			}
		}
	}

// htmlspecialcharsのショートカット
function h($value) {
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value) {
	return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>' , $value);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>ひとこと掲示板</title>

	<link rel="stylesheet" href="style.css" />
  	<link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
</head>

<body>
<div id="wrap">
  <div id="head">
    <h1>ひとこと掲示板</h1>
  </div>
  <div id="content">
  	<div style="text-align: right"><a href="logout.php">ログアウト</a></div>
    <form action="" method="post">
      <dl>
        <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
        <dd>
          <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
          <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
		  <input type="hidden" name="retweet_post_id" value="<?php echo h($_REQUEST['rt']); ?>" />
		 <input type="hidden" name="like" value="<?php echo h($_REQUEST['like']); ?>" />
        </dd>
      </dl>
      <div>
        <p>
          <input type="submit" value="投稿する" />
        </p>
      </div>
    </form>

<?php
foreach ($posts as $post):
?>
    <div class="msg">
    <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />

	                   <!-- //RTしたUSER NAME表示                -->
					   <?php
                if ($post['retweet_member_id'] !== 0) {
                    $usernames = $db->prepare('select p.*, m.name, m.id from posts  p join members  m on p.retweet_member_id = m.id where retweet_member_id = ? order by m.id ');
                    $usernames->execute(array(
                        $post['retweet_member_id'],
                    ));
                    $username = $usernames->fetch();?>
                    <?php if ($post['retweet_post_id']>0):
                    ?>
                    <p class="day" style=><?php echo h($username['name']); ?>がリツイートしました</p>
                        <?php endif;?>
                <?php }?>
                <!-- RT機能 -->
                <!-- カウント表示 -->
                <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>] 
            <?php 
            //必要データ取得
            echo 'あああ';
            $retweet  = $db->prepare('select id, message , member_id, retweet_post_id, retweet_member_id from posts where id=? order by created desc '); 
            $retweet->execute(array($_REQUEST['rt']));
            $rt_msg = $retweet->fetch();
            //カウント
            $retweets_total=$db->prepare('select count(retweet_post_id) as rt_cnt from posts where retweet_post_id =? and retweet_member_id > 0 ');
            //元投稿
            if((int)$rt_msg['retweet_post_id'] === 0){
                echo 'いいい';
                $retweets_total->execute(array($rt_msg['id'] ));
                $retweet_total=$retweets_total->fetch();
            }
            //RT
            elseif((int)$rt_msg['retweet_post_id'] !== 0){
                echo 'ううう';
                $retweets_total->execute(array($rt_msg['retweet_post_id'] ));
                $retweet_total=$retweets_total->fetch();
            }
            // RTされていない投稿
            if((int)$retweet_total['rt_cnt']===0)
            { 
                echo 'えええ';
                ?>
       [<a href="index.php?rt=<?php echo h($post['id']); ?>" style="color:blue; text-decoration:none;" ><span>RT
           </span></a>]
           <?php
        //RT数のある投稿
        //元投稿
    }elseif((int)$retweet_total['rt_cnt'] >=1 and (int)$rt_msg['retweet_post_id'] ===0) 
    {
        echo 'おおお';
        ?>          
        [<a href="index.php?rt=<?php echo h($post['id']); ?>" style="color:DarkCyan; text-decoration:none;" "><span><?php  echo h($retweet_total['rt_cnt']);?>RT
    </span></a>]                        
    <?php
        //RT
    }elseif((int)$retweet_total['rt_cnt'] >=1 and (int)$rt_msg
    ['retweet_post_id'] !==0) 
    {
        echo 'カカか';
        ?>          
[<a href="index.php?rt=<?php echo h($post['id']); ?>" style="color:DarkCyan; text-decoration:none;" "><span><?php  echo h($retweet_total['rt_cnt']);?>RT
</span></a>]                        
<?php  }?>


<!-- いいねボタン -->
<?php     
            $likes_total = $db->prepare('select count(post_id) as cnt from likes where post_id =?  group by post_id');               
            //元投稿
            if((int)$post['retweet_post_id'] === 0){
                $likes_total ->execute(array(
                    $post['id']
                ));
                $like_total = $likes_total->fetch();
                //RT
            }elseif((int)$post['retweet_post_id'] !== 0){
                $likes_total ->execute(array(
                    $post['retweet_post_id']
                ));
                $like_total = $likes_total->fetch();    
            }
            if((int)$like_total['cnt'] ===0 )
            {?>
                [<a href="index.php?like=<?php echo h($post['id']); ?>" style="color:pink; text-decoration:none;"><span id="like"><i class="fas fa-heart"></i><?php echo h($like_total['cnt']); ?></span></a>]
            <?php
                }elseif((int)$like_total['cnt']  >= 1 ){?>
                [<a href="index.php?like=<?php echo h($post['id']); ?>" style="color:red; text-decoration:none;"><span id="like"><i class="fas fa-heart"></i><?php echo h($like_total['cnt']); ?></span></a>]
    
                <?php } ?>           
                </p>
 
    <p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>   
		<?php
if ($post['reply_post_id'] > 0):
?>
<a href="view.php?id=<?php echo
h($post['reply_post_id']); ?>">
返信元のメッセージ</a>
<?php
endif;
?>
<?php
if ($_SESSION['id'] == $post['member_id']):
?>
[<a href="delete.php?id=<?php echo h($post['id']); ?>"
style="color: #F33;">削除</a>]
<?php
endif;
?>
    </p>
    </div>
<?php
endforeach;
?>

<ul class="paging">
<?php
if ($page > 1) {
?>
<li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
<?php
} else {
?>
<li>前のページへ</li>
<?php
}
?>
<?php
if ($page < $maxPage) {
?>
<li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
<?php
} else {
?>
<li>次のページへ</li>
<?php
}
?>
</ul>
  </div>
</div>
</body>
</html>

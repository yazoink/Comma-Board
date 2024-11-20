<?php
# todo: jump to reply, max posts at a time, name/id formatting, copy post url, captcha
require "misc/vars.php";

date_default_timezone_set($TIMEZONE);

$message_blank = false;
$messages_csv = "misc/csv/messages.csv";

# handle messages per page
if (isset($_COOKIE["max_per_page"])) {
  $max_per_page = $_COOKIE["max_per_page"];
} else {
  $max_per_page = $DEFAULT_MAX_PER_PAGE;
}

if (array_key_exists("max_per_page", $_POST)) {
  $max_per_page = (int) $_POST["max_per_page"];
  setcookie("max_per_page", $max_per_page, time() + (86400 * 30), "/");
}

# read csv to $messages array
$header = NULL;
$messages = array();
$csv = fopen($messages_csv, "r");
while ($row = fgetcsv($csv)) {
  if (!$header) {
    $header = $row;
  } else {
    $messages[] = array_combine($header, $row);
  }
}
fclose($csv);

$messages = array_reverse($messages);

# calculate info for pages
$message_num = count($messages);
$max_pages = ceil($message_num / $max_per_page);

if (array_key_exists("page", $_GET)) {
  $current_page = (int) $_GET["page"];
  if ($current_page < 1 || !is_numeric($current_page) || $current_page > $max_pages) {
    $current_page = 1;
  }
} else {
  $current_page = 1;
}

$next_page = $current_page + 1;
$previous_page = $current_page - 1;

$start_index = $max_per_page * ($current_page - 1);

if ($start_index >= $message_num) {
  if ($message_num % $max_per_page == 0) {
    $start_index = $max_per_page * ($message_num / $max_per_page) - ($max_per_page * 2);
  } else {
    $start_index = $max_per_page * ($message_num / $max_per_page) - ($message_num % $max_per_page);
  }
}

if ($message_num - $start_index < $max_per_page) {
  $end_index = $start_index + ($message_num - $start_index);
} else {
  $end_index = $start_index + $max_per_page;
}

# handle if post was submitted
if (array_key_exists("message", $_POST) && array_key_exists("name", $_POST)) {
  if (trim($_POST["message"]) == "") {
    $message_blank = true;
  } else {
    $message = strip_tags($_POST["message"]);
    $message = str_replace("\n", "<br>\n", $message);

    if (trim($_POST["name"]) == "") {
      $name = $NAME_PLACEHOLDER;
    } else {
      $name = strip_tags($_POST["name"]);
    }
    $datetime = date("l jS \of F Y h:i A");

    $message = array(time(), $name, $datetime, $message);
    $csv = fopen($messages_csv, "a");
    fputcsv($csv, $message);
    fclose($csv);
    unset($_POST);
    header("Location: " . $_SERVER['PHP_SELF']);
  }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title><?php echo $BOARD_NAME; ?></title>
    <link rel="stylesheet" type="text/css" href="/static/css/style.css"/>
  </head>
  <body>
    <script>
    function insertText(textarea, text) {
      const position = textarea.selectionStart;
      const before = textarea.value.substring(0, position);
      const after = textarea.value.substring(position);
      textarea.value = before + text + after;
      textarea.selectionStart = textarea.selectionEnd = position + text.length;
    }

    function addReply(url) {
      const textarea = document.getElementById("message-content");
      insertText(textarea, `${url}\n`);
    }
    </script>
    <div class="container">
      <header>
        <a href="index.php"><img src="/static/images/logo.webp"></a>
      </header>
      <aside>
        <h2>About</h2>
        <p><?php echo $BOARD_NAME; ?> is a simple message board which uses a CSV file as a database. I don't have an good reason for not using a database, other than they're annoying.</p>
        <br>
        <p>You can reply to posts by clicking post IDs. Greentext is also supported.</p>
        <img src="/static/images/flower.webp">
        <br>
        <section class="submit">
          <h2>Submit a Post</h2>
          <?php 
          if ($message_blank == true) {
            echo "<p>Could not submit blank message.</p>";
          }
          ?>
          <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
            <label for="name"><b>Name</b></label><br>
            <input type="text" class="input" name="name" placeholder="<?php echo $NAME_PLACEHOLDER; ?>"><br>
            <br>
            <label for="message"><b>Message</b></label><br>
            <textarea id="message-content" class="input" maxlength="<?php echo $MAX_MESSAGE_LENGTH; ?>" name="message" rows="10" cols="20" placeholder="<?php echo $MESSAGE_PLACEHOLDER; ?>"></textarea><br>
            <br>
            <input type="submit" class="input" value="Submit">
          </form>
          <br>
        </section>
      </aside>
      <main>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
          <label for="max_per_page"><b>Maximum per page </b></label>
          <?php
          echo "<select name='max_per_page' class='input'>";
          for ($i = 5; $i <= 30; $i += 5) {
            if ($i == $max_per_page) {
            echo "<option selected value='$i'>$i</option>";
            } else {
            echo "<option value='$i'>$i</option>";
            }
          }
          echo "</select>";
          ?>
          <input type="submit" class="input" value="Refresh">
        </form>
        <section class="messages">
          <?php
          for ($i = $start_index; $i < $end_index; $i++) {
            $message_text = $messages[$i]["message"];

            $reply_regex = "/reply_to:[0-9]{1,10}/";
            preg_match_all($reply_regex, $message_text, $matches);
            foreach ($matches as $match) {
              foreach ($match as $m) {
                $id = str_replace("reply_to:", "", $m);
                for ($j = 0; $j < $message_num; $j++) { # find message index
                  if ($messages[$j]["id"] == $id) {
                    break;
                  }
                }
                # find page message is on
                $message_page = 1;
                for ($k = $max_per_page; $k <= $message_num; $k += $max_per_page) {
                  if ($j <= $k) {
                    break;
                  }
                  $message_page += 1;
                }
                $message_text = str_replace($m, "<b><i>Replying to <a href='index.php?page=" . $message_page . "#" . $id . "'>" . $id . "</a></i></b>", $message_text);
              }
            }

            # handle greentext
            $greentext_regex = "/^>.*$/m";
            preg_match_all($greentext_regex, $message_text, $matches);
            foreach ($matches as $match) {
              foreach ($match as $m) {
                $message_text = str_replace($m, "<span class='greentext'>$m</span>", $message_text);
              }
            }
            
            # print message
            echo "<div class='message'>";
            echo "<h3>{$messages[$i]['name']}</h3>";
            printf("<h4><i>%s</i></h4>", $messages[$i]["date"]);
            printf("<p>%s</p><br>", $message_text);
            printf(
              "<p>Post ID: <a href='javascript:;' id='%s' onclick='addReply(\"%s\")'>%s</a></p>",
              $messages[$i]["id"], 
              "reply_to:" . $messages[$i]["id"],
              $messages[$i]["id"]
            );
            echo "</div>";
          }

          # print prev/next
          if ($max_pages > 1) {
            echo "<p class='center'>";
            if ($current_page > 1) {
              echo "<a href='index.php?nav=Gallery&page={$previous_page}'>&#8810; Previous</a> ";
            }

            for ($i = 1; $i <= $max_pages; $i++) {
              if ($i == $current_page) {
                echo "$i ";
              } else {
                echo "<a href='index.php?nav=Gallery&page={$i}'>{$i}</a> ";
              }
            }

            if ($current_page < $max_pages) {
              echo "<a href='index.php?nav=Gallery&page={$next_page}'>Next &#8811;</a> ";
            }
            echo "</p>";
          }
          ?>
        </section>
      </main>
      <footer class="footer">
        <img src="/static/images/separator3.webp">
      </footer>
    </div>
  </body>
</html>

<?php
session_start();
$success = false;
$error = "";
/** @var \PDO */
$PDO = new PDO('mysql:dbname=factura;host=127.0.0.1', 'factura', 'factura');
require './phpmailer/Exception.php';
require './phpmailer/PHPMailer.php';
require './phpmailer/SMTP.php';
if (!(isset($_SESSION['user']) && isset($_SESSION['id']))) {
  if (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['login'])) {
    $statement = $PDO->prepare("SELECT * FROM users WHERE `username` = ? AND `password` = ?");
    $statement->execute([$_POST['username'], $_POST['password']]);
    $result = $statement->fetchAll();
    if (empty($result)) {
      $error = "Usuario/Contraseña incorrecto";
    } else {
      $success = true;
      $_SESSION['user'] = $_POST['username'];
      $_SESSION['id'] = $result[0]['id'];
      $statement = $PDO->prepare("SELECT * FROM `cart` WHERE `user_id` = ? LIMIT 1");
      $statement->execute([$_SESSION['id']]);
      $result = $statement->fetchAll(PDO::FETCH_ASSOC);
      if (empty($result)) {
        $statement = $PDO->prepare("INSERT INTO `cart` (user_id) VALUES (?)");
        $statement->execute([$_SESSION['id']]);
        $statement = $PDO->prepare("SELECT * FROM `cart` WHERE `user_id` = ? LIMIT 1");
        $statement->execute([$_SESSION['id']]);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
      }
      $cart = $result[0];
    }
  }
} else {
    $success = true;
    $statement = $PDO->prepare("SELECT * FROM `cart` WHERE `user_id` = ? LIMIT 1");
    $statement->execute([$_SESSION['id']]);
    $result = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (empty($result)) {
      $statement = $PDO->prepare("INSERT INTO `cart` (user_id) VALUES (?)");
      $statement->execute([$_SESSION['id']]);
      $statement = $PDO->prepare("SELECT * FROM `cart` WHERE `user_id` = ? LIMIT 1");
      $statement->execute([$_SESSION['id']]);
      $result = $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    $cart = $result[0];
    if (isset($_POST['addCart']) && isset($_POST['id']) && isset($_POST['cantidad'])) {
      if (!empty($_POST['cantidad'])) {
        $statement = $PDO->prepare("INSERT INTO `cart_has_products` (cart_id, product_id, quantity) VALUES (?,?,?);");
        $statement->execute([$cart['id'], $_POST['id'], $_POST['cantidad']]);
      }
    } else if (isset($_POST['logout'])) {
      session_destroy();
      header("Location: /");
    } else if (isset($_POST['sendEmail'])) {
      $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
      try {
        // Server
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jlrios2001@gmail.com';
        $mail->Password   = 'btclnaitpkzsuudq';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        //Recipients
        $mail->setFrom('jlrios2001@gmail.com', 'Tiendita');
        $mail->addAddress('jlopez_2001@hotmail.com');
        $mail->addReplyTo('jlrios2001@gmail.com', 'Tiendita');

        // Body
        //Inicio PDF

        // Incluye el archivo de la biblioteca TCPDF
        require('TCPDF/tcpdf.php');

        // Crea una instancia de TCPDF
        $pdf = new TCPDF();

        // Agrega una nueva página
        $pdf->AddPage();

        // Agrega contenido al PDF
        $pdf->SetFont('times', '', 12);
        $pdf->Cell(0, 10, 'Detalles de la compra', 0, 1);

        // ... (Agrega aquí el contenido específico de la compra como en tu ejemplo)
        $statement = $PDO->prepare(<<<SQL
            SELECT 
                products.id, products.name, products.price, cart_has_products.quantity 
            FROM cart_has_products  
            INNER JOIN products ON cart_has_products.product_id = products.id
            WHERE cart_id = ?
        SQL);
        $statement->execute([$cart['id']]);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Agrega la tabla al PDF
        $pdf->SetFont('times', '', 12);
        $pdf->SetFillColor(200, 200, 200); // Color de fondo de las celdas de encabezado
        $pdf->Cell(30, 10, '#', 1, 0, 'C', 1);
        $pdf->Cell(60, 10, 'Producto', 1, 0, 'C', 1);
        $pdf->Cell(40, 10, 'Precio unitario', 1, 0, 'C', 1);
        $pdf->Cell(40, 10, 'Cantidad', 1, 0, 'C', 1);
        $pdf->Cell(40, 10, 'Precio total', 1, 1, 'C', 1);

        $granTotal = 0;
        foreach ($result as $key => $row) {
            $key = $key + 1;
            $name = (string)$row['name'];
            $unitPrice = (float)$row['price'];
            $quantity = (int)$row['quantity'];
            $total = $unitPrice * $quantity;
            $granTotal = $granTotal + $total;

            // Agrega una fila a la tabla en el PDF
            $pdf->Cell(30, 10, $key, 1, 0, 'C');
            $pdf->Cell(60, 10, $name, 1, 0, 'C');
            $pdf->Cell(40, 10, $unitPrice, 1, 0, 'C');
            $pdf->Cell(40, 10, $quantity, 1, 0, 'C');
            $pdf->Cell(40, 10, $total, 1, 1, 'C');
        }

        // Agrega el total al PDF
        $pdf->Cell(170, 10, 'Total:', 1, 0, 'R');
        $pdf->Cell(40, 10, $granTotal, 1, 1, 'C');


        // Genera el contenido del PDF
        $contenido_pdf = $pdf->Output('', 'S');

        // Cierra el documento PDF para liberar recursos
        $pdf->close();

        // Adjuntar el archivo PDF al correo electrónico
        $mail->addStringAttachment($contenido_pdf, 'compra.pdf');

      
        //Fin PDF

        $statement = $PDO->prepare(<<<SQL
              SELECT 
                products.id, products.name, products.price, cart_has_products.quantity 
              from cart_has_products  
              INNER JOIN products
              ON cart_has_products.product_id = products.id
              WHERE cart_id = ?
            SQL);
        $statement->execute([$cart['id']]);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        $HTML = '<h3>Resumen de tu pedido</h3>
              <table class="table">
                <thead>
                  <tr>
                    <th scope="col">#</th>
                    <th scope="col">Producto</th>
                    <th scope="col">Precio unitario</th>
                    <th scope="col">Cantidad</th>
                    <th scope="col">Precio total</th>
                  </tr>
                </thead>
                <tbody>';
        $granTotal = 0;
        foreach ($result as $key => $row) {
          $key = $key + 1;
          $name = (string)$row['name'];
          $unitPrice = (float)$row['price'];
          $quantity = (int)$row['quantity'];
          $total = $unitPrice * $quantity;
          $granTotal = $granTotal + $total;
          $HTML = $HTML . <<<HTML
                      <tr>
                        <th scope="row">$key</th>
                        <td>$name</td>
                        <td>$unitPrice</td>
                        <td>$quantity</td>
                        <td>$total</td>
                      </tr>
                    HTML;
        }
        $HTML = $HTML . '
                </tbody>
              </table>
              <h4>Total: ' . $granTotal . '</h4>';

        //Content
        $mail->isHTML(true);
        $mail->Subject = 'Recibo de compra';
        //$mail->Body    = $HTML;
        $mail->Body = 'Adjunto encontrarás los detalles de la compra en formato PDF.';
        $mail->AltBody = 'Se requiere de HTML :c';

        $mail->send();

        $statement = $PDO->prepare('DELETE FROM cart_has_products where cart_id = ?');
        $statement->execute([$cart['id']]);
        $statement->execute();
      } catch (\Exception $e) {
        die('No se envió correo. Razón: ' . $e->getMessage());
      }

    }
  }

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link rel="stylesheet" href="./styles/bootstrap.min.css">
</head>

<body>
  <div class="container">
    <?php if (!$success) : ?>
      <?php if (strlen($error) !== 0) echo "<div class=\"alert alert-danger\" role=\"alert\">{$error}</div>"; ?>
      <form method="post" target="/">
        <input type="hidden" name="login" value="login">
        <div class="mb-3">
          <label for="username" class="form-label">Usuario</label>
          <input type="text" class="form-control" id="username" name="username">
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">Contraseña</label>
          <input type="password" class="form-control" id="password" name="password">
        </div>
        <button type="submit" class="btn btn-primary">Iniciar</button>
      </form>
    <?php else : ?>
      <div class="row">
        <div class="col">
          <h2>Iniciaste sesión como: <?php echo $_SESSION['user'] ?></h2>
        </div>
        <div class="col">
          <form method="post" target="/">
            <input type="hidden" name="logout" value="logout">
            <button type="submit" class="btn btn-primary">Cerrar sesión</button>
          </form>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <h2>Productos disponibles</h2>
          <table class="table">
            <thead>
              <tr>
                <th scope="col">ID</th>
                <th scope="col">Producto</th>
                <th scope="col">Precio</th>
                <th scope="col">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $statement = $PDO->prepare("SELECT * FROM products;");
              $statement->execute();
              $result = $statement->fetchAll(PDO::FETCH_ASSOC);
              foreach ($result as $row) {
                $id = $row['id'];
                $name = $row['name'];
                $price = $row['price'];
                $actions = <<<HTML
                  <form method="post" action="/">
                    <input type="hidden" name="addCart" value="addCart">
                    <div class="row g-1 align-items-center">
                      <input type="hidden" name="id" value="$id">
                      <div class="col-auto">
                        <label for="cantidad" class="col-form-label">Cantidad</label>
                      </div>
                      <div class="col-auto">
                        <input type="number" name="cantidad" id="cantidad" class="form-control" >
                      </div>
                      <div class="col-auto">
                        <input type="submit" class="btn btn-primary form-control" value="Agregar al carrito">
                      </div>
                    </div>
                  </form>
                HTML;
                echo <<<HTML
                  <tr>
                    <th scope="row">$id</th>
                    <td>$name</td>
                    <td>$price</td>
                    <td>$actions</td>
                  </tr>
                HTML;
              }
              ?>
            </tbody>
          </table>
        </div>
        <div class="col">
          <h2>Tu carrito</h2>
          <?php
          $statement = $PDO->prepare(<<<SQL
            SELECT 
              products.id, products.name, products.price, cart_has_products.quantity 
            from cart_has_products  
            INNER JOIN products
            ON cart_has_products.product_id = products.id
            WHERE cart_id = ?
          SQL);
          $statement->execute([$cart['id']]);
          $result = $statement->fetchAll(PDO::FETCH_ASSOC);
          if (empty($result)) {
            echo "<h3>Tu carrito esta vacío</h3>";
          } else {
          ?>
            <h3>Resumen de tu pedido</h3>
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">#</th>
                  <th scope="col">Producto</th>
                  <th scope="col">Precio unitario</th>
                  <th scope="col">Cantidad</th>
                  <th scope="col">Precio total</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $granTotal = 0;
                foreach ($result as $key => $row) {
                  $key = $key + 1;
                  $name = (string)$row['name'];
                  $unitPrice = (float)$row['price'];
                  $quantity = (int)$row['quantity'];
                  $total = $unitPrice * $quantity;
                  $granTotal = $granTotal + $total;
                  echo <<<HTML
                    <tr>
                      <th scope="row">$key</th>
                      <td>$name</td>
                      <td>$unitPrice</td>
                      <td>$quantity</td>
                      <td>$total</td>
                    </tr>
                  HTML;
                }
                ?>

              </tbody>
            </table>
            <h4>Total: <?php echo $granTotal; ?></h4>
            <form action="/" method="post">
              <input type="hidden" name="sendEmail" value="sendEmail">
              <input type="submit" value="Comprar">
            </form>
          <?php
          }
          ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <script src="./js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/token.php';
require_once __DIR__ . '/../utils/filesUpload.php';
class User
{
    private $dataBase;
    private $table = 'User';
    private $token;
    private $fileUploader;
    // private $baseUrl = 'http://localhost:4200/back';
    private $baseUrl = 'http://stand2.progoff.ru/back';

    // конструктор класса User
    public function __construct(DataBase $dataBase)
    {
        $this->dataBase = $dataBase;
        $this->token = new Token();
        $this->fileUploader = new FilesUpload();
    }

    public function create($userData)
    {
        $userData = (object) $this->dataBase->stripAll((array)$userData);

        // Вставляем запрос
        $userData->password = password_hash($userData->password, PASSWORD_BCRYPT);

        if ($this->emailExists($userData->email)) {
            throw new Exception('Пользователь уже существует');
        }
        $query = $this->dataBase->genInsertQuery(
            $userData,
            $this->table
        );

        // подготовка запроса
        $stmt = $this->dataBase->db->prepare($query[0]);
        if ($query[1][0] != null) {
            $stmt->execute($query[1]);
        }
        $userId = $this->dataBase->db->lastInsertId();
        if ($userId) {
            $tokens = $this->token->encode(array("id" => $userId));
            $this->addRefreshToken($tokens["refreshToken"], $userId);
            return $tokens;
        }
        return null;
    }

    // Получение пользовательских ролей
    public function getRoles()
    {
        $query = "SELECT * FROM UserRole";
        return $this->dataBase->db->query($query)->fetchAll();;
    }

    // Получение пользовательской информации
    public function read($userId)
    {
        $query = "SELECT u.name, surname, lastname, email, phone, u.roleId, ur.name as roleName FROM $this->table u JOIN UserRole ur ON u.roleId = ur.id WHERE u.id=$userId";
        $user = $this->dataBase->db->query($query)->fetch();
        $user['roleId'] = $user['roleId'] * 1;
        return $user;
    }

    public function readShortView($userId)
    {
        $query = "SELECT u.id, u.name, surname, lastname, u.roleId, ur.name as roleName FROM $this->table u JOIN UserRole ur ON u.roleId = ur.id WHERE u.id=$userId";
        $user = $this->dataBase->db->query($query)->fetch();
        $user['roleId'] = $user['roleId'] * 1;
        return $user;
    }

    public function getProfileInfo($userId)
    {
        $info = array(
            "documents" => $this->getDocuments($userId),
            "company" => $this->getCompanyInfo($userId),
            "completeOrders" => [
                array("value" => 1000, "name" => "01.07.21"),
                array("value" => 1500, "name" => "08.07.21"),
                array("value" => 900, "name" => "15.07.21"),
                array("value" => 1200, "name" => "22.07.21"),
                array("value" => 1800, "name" => "29.07.21")
            ],
            "views" => [
                array("value" => 1000, "name" => "01.07.21"),
                array("value" => 1500, "name" => "08.07.21"),
                array("value" => 900, "name" => "15.07.21"),
                array("value" => 1200, "name" => "22.07.21"),
                array("value" => 1800, "name" => "29.07.21")
            ],
            "totalSums" => [
                array("value" => 10000, "name" => "01.07.21"),
                array("value" => 14000, "name" => "08.07.21"),
                array("value" => 9000, "name" => "15.07.21"),
                array("value" => 13000, "name" => "22.07.21"),
                array("value" => 15000, "name" => "29.07.21")
            ],
        );
        return $info;
    }

    private function getDocuments($userId)
    {
        $query = "SELECT d.id, d.name, files.file FROM (SELECT file, documentId FROM UserDocument WHERE userId = $userId) as files RIGHT JOIN Document d ON d.id=files.documentId ORDER BY d.id";
        return $this->dataBase->db->query($query)->fetchAll();
    }

    private function getDocumentFile($userId, $documentId)
    {
        $query = "SELECT file FROM UserDocument WHERE userId = $userId AND documentId=$documentId";
        $stmt = $this->dataBase->db->query($query);

        return $stmt->fetch()['file'];
    }

    public function addDocument($userId, $document, $file)
    {
        if(!$file){
            throw new Exception('Загрузите файл');
        }
        $document = $this->dataBase->stripAll((array)$document);
        $userFile = $this->getDocumentFile($userId, $document['documentId']);
        if ($userFile) {
            $this->fileUploader->removeFile($userFile);
        }
        $userFile = $this->fileUploader->upload($file, 'UserFiles', uniqid());
        $query = $this->dataBase->genInsertQuery(
            array(
                "userId" => $userId,
                "documentId" => $document['documentId'],
                "file" => $userFile
            ),
            "UserDocument"
        );
        $stmt = $this->dataBase->db->prepare($query[0]);
        if ($query[1][0] != null) {
            $stmt->execute($query[1]);
        }

        return $userFile;
    }

    public function deleteDocument($userId, $documentId)
    {
        $documentId = $this->dataBase->strip($documentId);
        $userFile = $this->getDocumentFile($userId, $documentId);
        if ($userFile) {
            $this->fileUploader->removeFile($userFile);
            $query = "DELETE FROM UserDocument WHERE userId=$userId AND documentId=$documentId";
            $this->dataBase->db->query($query);
        }
        return true;
    }

    private function getCompanyInfo($userId)
    {
        $query = "SELECT * FROM UserCompany WHERE userId=$userId";
        $company = $this->dataBase->db->query($query)->fetch();
        if ($company) {
            $company['createDate'] = $company['createDate'] ? date("Y/m/d H:i:s", strtotime($company['createDate'])) : null;
            $company['taxRegistrationDate'] = $company['taxRegistrationDate'] ? date("Y/m/d H:i:s", strtotime($company['taxRegistrationDate'])) : null;
            unset($company['id']);
            unset($company['userId']);
            return $company;
        }
        return null;
    }

    public function addCompanyInfo($userId, $data)
    {
        $data = $this->dataBase->stripAll((array)$data);
        $data['userId'] = $userId;
        $query = $this->dataBase->genInsertQuery(
            $data,
            "UserCompany"
        );

        $stmt = $this->dataBase->db->prepare($query[0]);
        if ($query[1][0] != null) {
            $stmt->execute($query[1]);
        }

        return true;
    }

    public function updateCompanyInfo($userId, $request)
    {
        $request = $this->dataBase->stripAll((array)$request);

        $query = $this->dataBase->genUpdateQuery(
            $request,
            "UserCompany",
            $userId,
            "userId"
        );

        $stmt = $this->dataBase->db->prepare($query[0]);
        $stmt->execute($query[1]);
        return true;
    }

    public function update($userId, $request)
    {
        $request = $this->dataBase->stripAll((array)$request);
        if (isset($request['password'])) {
            unset($request['password']);
        }
        if (isset($request['email'])) {
            unset($request['email']);
        }

        $query = $this->dataBase->genUpdateQuery(
            $request,
            $this->table,
            $userId
        );

        $stmt = $this->dataBase->db->prepare($query[0]);
        $stmt->execute($query[1]);
        return true;
    }

    public function getUsers()
    {
        $query = "SELECT login, email, phone FROM " . $this->table;
        $stmt = $this->dataBase->db->query($query);
        $users = [];
        while ($user = $stmt->fetch()) {
            $user = $user;
            $users[] = $user;
        }
        return $users;
    }

    public function checkAdmin($userId)
    {
        $query = "SELECT isAdmin FROM $this->table WHERE id = $userId";
        $stmt = $this->dataBase->db->query($query);
        if ($stmt->fetch()['isAdmin']) {
            return true;
        }
        return false;
    }

    public function getUserImage($userId)
    {
        $query = "SELECT image FROM $this->table WHERE id = $userId";
        $stmt = $this->dataBase->db->query($query);

        return $stmt->fetch()['image'];
    }

    public function login($email, $password)
    {
        if ($email != null) {
            $sth = $this->dataBase->db->prepare("SELECT id, password FROM " . $this->table . " WHERE email = ? LIMIT 1");
            $sth->execute(array($email));
            $fullUser = $sth->fetch();
            if ($fullUser) {
                if (!password_verify($password, $fullUser['password'])) {
                    throw new Exception("User not found", 404);
                }
                $tokens = $this->token->encode(array("id" => $fullUser['id']));
                $this->addRefreshToken($tokens["refreshToken"], $fullUser['id']);
                return $tokens;
            } else {
                throw new Exception("User not found", 404);
            }
        } else {
            return array("message" => "Введите данные для регистрации");
        }
    }

    public function isRefreshTokenActual($token, $userId)
    {
        $query = "SELECT id FROM RefreshTokens WHERE token = ? AND userId = ?";

        // подготовка запроса
        $stmt = $this->dataBase->db->prepare($query);
        // инъекция
        $token = htmlspecialchars(strip_tags($token));
        $userId = htmlspecialchars(strip_tags($userId));
        // выполняем запрос
        $stmt->execute(array($token, $userId));

        // получаем количество строк
        $num = $stmt->rowCount();

        if ($num > 0) {
            return true;
        }

        return $num > 0;
    }

    // Обновление пароля
    public function updatePassword($userId, $password)
    {
        if ($userId) {
            $password = json_encode(password_hash($password, PASSWORD_BCRYPT));
            $query = "UPDATE $this->table SET password = '$password' WHERE id=$userId";
            $stmt = $this->dataBase->db->query($query);
        } else {
            return array("message" => "Токен неверен");
        }
    }

    // Отправление сообщений

    public function sendMessage($userId, $request)
    {
        $request = $this->dataBase->stripAll($request);
        $request['userId'] = $userId;
        $query = $this->dataBase->genInsertQuery(
            $request,
            'messages'
        );
        $stmt = $this->dataBase->db->prepare($query[0]);
        if ($query[1][0] != null) {
            $stmt->execute($query[1]);
        }
    }

    public function addRefreshToken($tokenn, $userId)
    {
        $query = "DELETE FROM RefreshTokens WHERE userId=$userId";
        $this->dataBase->db->query($query);
        $query = "INSERT INTO RefreshTokens (token, userId) VALUES ('$tokenn', $userId)";
        $this->dataBase->db->query($query);
    }

    public function removeRefreshToken($token)
    {
        $userId = $this->token->decode($token, true)->data->id;
        $query = "DELETE FROM RefreshTokens WHERE userId = $userId";
        $this->dataBase->db->query($query);
    }

    public function refreshToken($token)
    {
        $userId = $this->token->decode($token, true)->data->id;

        if (!$this->isRefreshTokenActual($token, $userId)) {
            throw new Exception("Unauthorized", 401);
        }

        $this->removeRefreshToken($userId);

        $tokens = $this->token->encode(array("id" => $userId));
        $this->addRefreshToken($tokens[1], $userId);
        return $tokens;
    }

    public function getUpdateLink($email)
    {
        $userId = $this->emailExists($email);
        $path = 'logs.txt';

        if (!$userId) {
            throw new Exception("Bad request", 400);
        }

        $tokens = $this->token->encode(array("id" => $userId));
        $url = $this->baseUrl . "/update?updatePassword=" . urlencode($tokens[0]);
        $subject = "Изменение пароля для jungliki.com";

        $message = "<h2>Чтобы изменить пароль перейдите по ссылке <a href='$url'>$url</a>!</h2>";

        $headers  = "Content-type: text/html; charset=utf-8 \r\n";

        mail($email, $subject, $message, $headers);
        file_put_contents($path, PHP_EOL . $email . " " . date("m.d.y H:i:s"), FILE_APPEND);
        return true;
    }

    private function emailExists(string $email)
    {
        $query = "SELECT id FROM " . $this->table . " WHERE email = ?";


        // подготовка запроса
        $stmt = $this->dataBase->db->prepare($query);
        // выполняем запрос
        $stmt->execute(array($email));

        // получаем количество строк
        $num = $stmt->rowCount();

        if ($num > 0) {
            return $stmt->fetch()['id'] * 1;
        }

        return false;
    }
}

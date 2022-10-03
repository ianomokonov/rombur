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

    // public function create($userData)
    // {
    //     $userData = (object) $this->dataBase->stripAll((array)$userData);

    //     // Вставляем запрос
    //     $userData->password = password_hash($userData->password, PASSWORD_BCRYPT);

    //     $query = $this->dataBase->genInsertQuery(
    //         $userData,
    //         $this->table
    //     );

    //     // подготовка запроса
    //     $stmt = $this->dataBase->db->prepare($query[0]);
    //     if ($query[1][0] != null) {
    //         $stmt->execute($query[1]);
    //     }
    //     $userId = $this->dataBase->db->lastInsertId();
    //     if ($userId) {
    //         $tokens = $this->token->encode(array("id" => $userId));
    //         $this->addRefreshToken($tokens["refreshToken"], $userId);
    //         return $tokens;
    //     }
    //     return null;
    // }

    public function readContent()
    {
        $query = "SELECT * FROM Content";
        $content = $this->dataBase->db->query($query)->fetchAll();
        return $content;
    }

    public function updateContent($data)
    {
        $request = $this->dataBase->stripAll((array)$data);
        $id = $request['id'];
        unset($request['id']);
        $query = $this->dataBase->genUpdateQuery(
            $request,
            "Content",
            $id
        );

        $stmt = $this->dataBase->db->prepare($query[0]);
        $stmt->execute($query[1]);
        return true;
    }

    private function getDocumentFile($userId, $documentId)
    {
        $query = "SELECT file FROM UserDocument WHERE userId = $userId AND documentId=$documentId";
        $stmt = $this->dataBase->db->query($query);

        return $stmt->fetch()['file'];
    }

    public function addDocument($userId, $document, $file)
    {
        if (!$file) {
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

    public function login($password)
    {
        if ($password != null) {
            $sth = $this->dataBase->db->prepare("SELECT id, password FROM " . $this->table . " LIMIT 1");
            $sth->execute(array());
            $fullUser = $sth->fetch();
            if ($fullUser) {
                if (!password_verify($password, $fullUser['password'])) {
                    throw new Exception("User not found", 404);
                }
                $tokens = $this->token->encode(array("id" => $fullUser['id']));
                $this->addRefreshToken($tokens["refreshToken"], $fullUser['id']);
                return $tokens;
            } else {
                throw new Exception("Неверный пароль", 404);
            }
        } else {
            throw new Exception("Введите пароль", 409);
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
}

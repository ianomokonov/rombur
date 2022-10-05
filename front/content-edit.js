import { get, post, put, REFRESH_TOKEN_KEY, TOKEN_KEY } from "./http.js";

// Получаем данные с бэка
let contentData;
let changedData = {};
try {
  contentData = await get("content");
} catch {
  console.warn("Ошибка загрузки контента");
}

// Блоки используемые в скрипте
const authModal = new bootstrap.Modal("#myModal");
const contentBlocks = document.querySelectorAll("[data-content-id]");
const imgContentBlocks = document.querySelectorAll("[data-img-content-id]");

// Переменные скрипта
const isAdmin = !!sessionStorage.getItem(TOKEN_KEY);

// Заполнение контента
contentBlocks.forEach((block) => {
  const blockData = contentData?.find((d) => d.id == block.dataset.contentId);
  if (blockData) {
    block.innerText = blockData.value;
  }
});

imgContentBlocks.forEach((block) => {
  const blockData = contentData?.find(
    (d) => d.id == block.dataset.imgContentId
  );
  if (blockData) {
    block.style.backgroundImage = `url(${blockData.value})`;
  }

  const div = document.createElement("div");
  div.innerHTML = `
    <div>
        <div class="edit-img-btn">Изменить</div>
        <input hidden name="images" type="file" accept="image/*">
    </div>
  `;

  block.append(div.firstElementChild);
});

//Отслеживание событий

// Отслеживание клика по всему
const onDocumentClick = ({ target }) => {
  if (target.closest("input[hidden]")) {
    return;
  }
  const isExitBtn = target.closest(".admin-panel__btn_exit");
  if (isExitBtn) {
    if (
      !Object.keys(changedData).length ||
      confirm("Вы уверены, что хотите выйти не опубликовав изменения?")
    ) {
      setAdmin(false);
    }

    return;
  }

  const publishBtn = target.closest(".admin-panel__btn_publish");
  if (publishBtn) {
    if (
      Object.keys(changedData).length &&
      confirm("Вы уверены, что хотите опубликовать изменения?")
    ) {
      const formData = new FormData();
      Object.keys(changedData).forEach((contentId) => {
        if (typeof changedData[contentId] == "string") {
          formData.set(contentId, changedData[contentId]);
          return;
        }
        formData.set(contentId, changedData[contentId], contentId);
      });

      post("content", formData, false).then(() => alert("Изменения сохранены"));
    }

    return;
  }

  const imgEditableBlock = target.closest("[data-img-content-id]");
  if (imgEditableBlock) {
    onUploadFileClick(imgEditableBlock);
  }
};

const onEditableBlur = ({ target }) => {
  if (!target.dataset.contentId) {
    return;
  }

  const initData = contentData?.find((d) => d.id == target.dataset.contentId);
  if (target.innerText !== initData?.value) {
    changedData[target.dataset.contentId] = target.innerText;
    console.log(changedData);
    return;
  }

  delete changedData[target.dataset.contentId];
  console.log(changedData);
};

// Обработка входа админа
document.addEventListener("keydown", (event) => {
  if (event.code == "KeyM" && event.ctrlKey) {
    authModal.show();
  }
});

// Обработка пароля админа
enterBtn.addEventListener("click", () => {
  post("login", { password: password.value })
    .then((tokens) => {
      sessionStorage.setItem(TOKEN_KEY, tokens.token);
      sessionStorage.setItem(REFRESH_TOKEN_KEY, tokens.refreshToken);
      setAdmin(true);
      authModal.hide();
    })
    .catch(() => {
      alert("Неверный пароль");
    });
});

setAdmin(isAdmin);

// Включение/Выключение режима админа
function setAdmin(isAdmin) {
  changedData = {};
  setEditable(isAdmin);
  if (isAdmin) {
    document.body.classList.add("admin");
    const div = document.createElement("div");
    div.innerHTML = `
        <div class="admin-panel d-flex align-items-center justify-content-between px-3 py-1">
            <span>Выполнен вход администратора</span>
            <div>
                <button class="btn btn-outline-light admin-panel__btn admin-panel__btn_publish">Опубликовать</button>
                <button class="btn btn-outline-light admin-panel__btn admin-panel__btn_exit ml-3">Выйти</button>
            </div>
        </div>
    `;
    document.body.insertBefore(
      div.firstElementChild,
      document.body.firstElementChild
    );
    document.addEventListener("click", onDocumentClick);
    return;
  }

  document.body.classList.remove("admin");
  sessionStorage.removeItem(TOKEN_KEY);
  sessionStorage.removeItem(REFRESH_TOKEN_KEY);
  document.removeEventListener("click", onDocumentClick);
  document.querySelector(".admin-panel")?.remove();
}

// Установка редактируемости блоков
function setEditable(isEditable) {
  contentBlocks.forEach((block) => {
    block.setAttribute("contenteditable", isEditable ? "true" : "false");
    if (isEditable) {
      block.addEventListener("focusout", onEditableBlur);
      return;
    }
    block.removeEventListener("focusout", onEditableBlur);
  });
  if (isEditable) {
    document.body.classList.add("admin");
    return;
  }
  document.body.classList.remove("admin");
}

function onUploadFileClick(block) {
  const fileInput = createUploadFileInput();
  block.append(fileInput);
  fileInput.addEventListener("change", (event) => {
    const file = event.target.files[0];
    const reader = new FileReader();

    reader.onload = ({ target }) => {
      block.style.backgroundImage = `url(${target.result.toString()})`;
    };

    reader.readAsDataURL(file);

    changedData[block.dataset.imgContentId] = file;
    console.log(changedData);
    fileInput.remove();
  });

  fileInput.click();
}

function createUploadFileInput() {
  const wrapper = document.createElement("div");

  wrapper.innerHTML = `
        <input hidden name="images" type="file" accept="image/*">
      `;

  return wrapper.firstElementChild;
}

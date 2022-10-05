export const TOKEN_KEY = "romBurToken";
export const REFRESH_TOKEN_KEY = "romBurRefreshToken";
const BASIS_URL = "http://stand2.progoff.ru/back";

export const get = async (url) => {
  const response = await fetch(`${BASIS_URL}/${url}`);
  return await response.json();
};

export const post = async (url, body, setContentType = true) => {
  const response = await fetch(`${BASIS_URL}/${url}`, {
    method: "POST",
    headers: getHeaders(setContentType),
    body: setContentType ? JSON.stringify(body) : body,
  });
  if (response.ok) {
    return await response.json();
  }
  throw new Error(await response.json());
};

export const put = async (url, body, setContentType = true) => {
  const response = await fetch(`${BASIS_URL}/${url}`, {
    method: "PUT",
    headers: getHeaders(setContentType),
    body: setContentType ? JSON.stringify(body) : body,
  });
  if (response.ok) {
    return await response.json();
  }
  throw new Error(await response.json());
};

function getHeaders(setContentType) {
  const headers = {};

  if (setContentType) {
    headers["Content-Type"] = "application/json";
  }

  const token = sessionStorage.getItem(TOKEN_KEY);

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  return headers;
}

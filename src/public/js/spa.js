function showToast(message)
{
  document.getElementById('divToast').style.display = 'block';
  document.getElementById('divToastMessage').textContent = message;
}

window.onload = function()
{
  function closeToast()
  {
    document.getElementById('divToast').style.display = 'none';
  }

  function setValue()
  {
    document.getElementById('divErrorTelegramCredentials').style.display = 'none';

    const value = document.getElementById('telegram_credentials').value;

    if (value == undefined || value == '')
    {
      document.getElementById('errorTelegramCredentials').textContent = 'Invalid telegram_credentials';
      document.getElementById('divErrorTelegramCredentials').style.display = 'block';
      return;
    }

    const xhr = new XMLHttpRequest();
    const formData = new FormData(form);
  
    xhr.addEventListener("load", function (event) {
      let response = JSON.parse(event.target.responseText);
      if (!response.error)
      {
        showToast('telegram_credentials successfully updated');
        setTimeout(function(){
          closeToast();
        }, 3000);
      }
      else {
        document.getElementById('errorTelegramCredentials').textContent = response.message;
        document.getElementById('divErrorTelegramCredentials').style.display = 'block';
      }
    });
  
    xhr.addEventListener("error", function (event) {
      console.log(event);
      document.getElementById('errorTelegramCredentials').textContent = 'Error trying to log in, try again';
      document.getElementById('divErrorTelegramCredentials').style.display = 'block';
    });
  
    xhr.open("POST", "./setTelegramCredentials");
    xhr.send(formData);
  }

  function getDbMetaValue()
  {
    document.getElementById('divErrorTelegramCredentials').style.display = 'none';

    const xhr = new XMLHttpRequest();
  
    xhr.addEventListener("load", function (event) {
      const response = JSON.parse(event.target.responseText);
      if (!response.error)
      {
        document.getElementById('telegram_credentials').value = response.metaValue;
      }
      else {
        document.getElementById('errorTelegramCredentials').textContent = response.message;
        document.getElementById('divErrorTelegramCredentials').style.display = 'block';
      }
    });
  
    xhr.addEventListener("error", function (event) {
      document.getElementById('errorTelegramCredentials').textContent = 'Error getting telegram_credentials';
      document.getElementById('divErrorTelegramCredentials').style.display = 'block';
    });
  
    xhr.open("GET", "./getTelegramCredentials");
    xhr.send();
  }
  
  function isAuthenticated()
  {
    document.getElementById('divErrorUsername').style.display = 'none';

    const xhr = new XMLHttpRequest();
  
    xhr.addEventListener("load", function (event) {
      if (!JSON.parse(event.target.responseText).authenticated)
      {
        document.getElementById('divLogin').style.display = 'block';
        document.getElementById('divValues').style.display = 'none';
  
        document.getElementById('username').disabled = false;
        document.getElementById('password').disabled = false;
        document.getElementById('loginButton').className = 'Polaris-Button Polaris-Button--primary';
  
        document.getElementById('telegram_credentials').disabled = true;
        document.getElementById('setValueButton').className = 'Polaris-Button Polaris-Button--disabled';
      }
      else {
        document.getElementById('divLogin').style.display = 'none';
        document.getElementById('divValues').style.display = 'block';
  
        document.getElementById('username').disabled = true;
        document.getElementById('password').disabled = true;
        document.getElementById('loginButton').className = 'Polaris-Button Polaris-Button--disabled';
  
        document.getElementById('telegram_credentials').disabled = false;
        document.getElementById('setValueButton').className = 'Polaris-Button Polaris-Button--primary';
        getDbMetaValue();
      }
    });
  
    xhr.addEventListener("error", function (event) {
      document.getElementById('errorUsername').textContent = 'Error getting auth status';
      document.getElementById('divErrorUsername').style.display = 'block';
    });
  
    xhr.open("GET", "./authenticated");
    xhr.send();
  }
  
  function logIn()
  {

    document.getElementById('divErrorUsername').style.display = 'none';

    const xhr = new XMLHttpRequest();
    const formData = new FormData(form);
  
    xhr.addEventListener("load", function (event) {
      
      let response = JSON.parse(event.target.responseText);
      if (!response.error)
      {
        isAuthenticated();
      }
      else {
        document.getElementById('errorUsername').textContent = 'Invalid credentials';
        document.getElementById('divErrorUsername').style.display = 'block';
        return;
      }
    });
  
    xhr.addEventListener("error", function (event) {
      document.getElementById('errorUsername').textContent = 'Error trying to log in';
      document.getElementById('divErrorUsername').style.display = 'block';
    });
  
    xhr.open("POST", "./wpLogin");
    xhr.send(formData);
  }

  function logInFormEnter(event)
  {
    if (event.key === 'Enter')
    {
        logIn();
    }
  }
  
  document.getElementById("buttonCloseToast").addEventListener("click", closeToast);
  document.getElementById("setValueButton").addEventListener("click", setValue);
  document.getElementById("loginButton").addEventListener("click", logIn);
  document.getElementById('password').addEventListener('keyup', logInFormEnter);
  document.getElementById('username').addEventListener('keyup', logInFormEnter);

  isAuthenticated();
}
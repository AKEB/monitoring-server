<?php
class T {
	/**
	 * MainPage
	 * 
	 * @return string "Main page"
	 * @return string "Главная страница"
	 */
	public static function MainPage(...$argv): string { return ''; }

	/**
	 * TestPage
	 * 
	 * @return string "Test page"
	 * @return string "Тестовая страница"
	 */
	public static function TestPage(...$argv): string { return ''; }

	/**
	 * Menu_Home
	 * 
	 * @return string "Home"
	 * @return string "Главная"
	 */
	public static function Menu_Home(...$argv): string { return ''; }

	/**
	 * Menu_Test
	 * 
	 * @return string "Test"
	 * @return string "Тест"
	 */
	public static function Menu_Test(...$argv): string { return ''; }

	/**
	 * Menu_Workers
	 * 
	 * @return string "Workers"
	 * @return string "Обработчики"
	 */
	public static function Menu_Workers(...$argv): string { return ''; }

	/**
	 * Worker_Menu
	 * 
	 * @return string "Workers"
	 * @return string "Обработчики"
	 */
	public static function Worker_Menu(...$argv): string { return ''; }

	/**
	 * Worker_PageTitle
	 * 
	 * @return string "Workers"
	 * @return string "Обработчики"
	 */
	public static function Worker_PageTitle(...$argv): string { return ''; }

	/**
	 * Worker_Permissions_Worker
	 * 
	 * @return string "Access"
	 * @return string "Доступ"
	 */
	public static function Worker_Permissions_Worker(...$argv): string { return ''; }

	/**
	 * Worker_Permissions_WorkerKeyHash
	 * 
	 * @return string "Worker key hash"
	 * @return string "Ключ обработчика"
	 */
	public static function Worker_Permissions_WorkerKeyHash(...$argv): string { return ''; }

	/**
	 * Worker_Permissions_CreateWorker
	 * 
	 * @return string "Create worker"
	 * @return string "Создание обработчика"
	 */
	public static function Worker_Permissions_CreateWorker(...$argv): string { return ''; }

	/**
	 * Worker_Table_Title
	 * 
	 * @return string "Title"
	 * @return string "Название"
	 */
	public static function Worker_Table_Title(...$argv): string { return ''; }

	/**
	 * Worker_Table_LastActiveTime
	 * 
	 * @return string "Last active time"
	 * @return string "Время последнего обращения"
	 */
	public static function Worker_Table_LastActiveTime(...$argv): string { return ''; }

	/**
	 * Worker_Table_CreateTime
	 * 
	 * @return string "Create time"
	 * @return string "Время создания"
	 */
	public static function Worker_Table_CreateTime(...$argv): string { return ''; }

	/**
	 * Worker_Table_UpdateTime
	 * 
	 * @return string "Update time"
	 * @return string "Время обновления"
	 */
	public static function Worker_Table_UpdateTime(...$argv): string { return ''; }

	/**
	 * Worker_Table_Actions
	 * 
	 * @return string "Actions"
	 * @return string "Действия"
	 */
	public static function Worker_Table_Actions(...$argv): string { return ''; }

	/**
	 * Worker_Table_Delete
	 * 
	 * @return string "Delete"
	 * @return string "Удалить"
	 */
	public static function Worker_Table_Delete(...$argv): string { return ''; }

	/**
	 * Worker_Delete_Title
	 * 
	 * @return string "Removing the worker"
	 * @return string "Удаление обработчика"
	 */
	public static function Worker_Delete_Title(...$argv): string { return ''; }

	/**
	 * Worker_Delete_Confirmation
	 * 
	 * @return string "Are you sure you want to delete the worker {title}?"
	 * @return string "Вы уверены, что хотите удалить обработчик {title}?"
	 */
	public static function Worker_Delete_Confirmation(...$argv): string { return ''; }

	/**
	 * Worker_Delete_WorkerNotFound
	 * 
	 * @return string "Worker not found"
	 * @return string "Обработчик не найден"
	 */
	public static function Worker_Delete_WorkerNotFound(...$argv): string { return ''; }

	/**
	 * Worker_Delete_PermissionDenied
	 * 
	 * @return string "You don\'t have permission to delete this worker"
	 * @return string "У вас нет прав на удаление этого обработчика"
	 */
	public static function Worker_Delete_PermissionDenied(...$argv): string { return ''; }

	/**
	 * Worker_Edit_CreateTitle
	 * 
	 * @return string "Create worker"
	 * @return string "Создание обработчика"
	 */
	public static function Worker_Edit_CreateTitle(...$argv): string { return ''; }

	/**
	 * Worker_Edit_EditTitle
	 * 
	 * @return string "Edit worker &quot;%s&quot; [ID=%d]"
	 * @return string "Редактирование обработчика &laquo;%s&raquo; [ID=%d]"
	 */
	public static function Worker_Edit_EditTitle(...$argv): string { return ''; }

	/**
	 * Worker_Edit_Title
	 * 
	 * @return string "Title"
	 * @return string "Название"
	 */
	public static function Worker_Edit_Title(...$argv): string { return ''; }

	/**
	 * Worker_Edit_LastActiveTime
	 * 
	 * @return string "Last active time"
	 * @return string "Время последнего обращения"
	 */
	public static function Worker_Edit_LastActiveTime(...$argv): string { return ''; }

	/**
	 * Worker_Edit_CreateTime
	 * 
	 * @return string "Create time"
	 * @return string "Время создания"
	 */
	public static function Worker_Edit_CreateTime(...$argv): string { return ''; }

	/**
	 * Worker_Edit_UpdateTime
	 * 
	 * @return string "Update time"
	 * @return string "Время обновления"
	 */
	public static function Worker_Edit_UpdateTime(...$argv): string { return ''; }

	/**
	 * Worker_Edit_WorkerKeyHash
	 * 
	 * @return string "Worker key hash"
	 * @return string "Ключ обработчика"
	 */
	public static function Worker_Edit_WorkerKeyHash(...$argv): string { return ''; }

	/**
	 * Worker_Edit_WorkerThreads
	 * 
	 * @return string "Worker threads"
	 * @return string "Количество потоков"
	 */
	public static function Worker_Edit_WorkerThreads(...$argv): string { return ''; }

	/**
	 * Worker_Edit_JobsGetTimeout
	 * 
	 * @return string "Jobs get timeout"
	 * @return string "Таймаут получения задач"
	 */
	public static function Worker_Edit_JobsGetTimeout(...$argv): string { return ''; }

	/**
	 * Worker_Edit_LoopTimeout
	 * 
	 * @return string "Loop timeout"
	 * @return string "Таймаут цикла"
	 */
	public static function Worker_Edit_LoopTimeout(...$argv): string { return ''; }

	/**
	 * Worker_Edit_ResponseSendTimeout
	 * 
	 * @return string "Response send timeout"
	 * @return string "Таймаут отправки ответа"
	 */
	public static function Worker_Edit_ResponseSendTimeout(...$argv): string { return ''; }

	/**
	 * Worker_Edit_LogsWriteTimeout
	 * 
	 * @return string "Logs write timeout"
	 * @return string "Таймаут записи логов"
	 */
	public static function Worker_Edit_LogsWriteTimeout(...$argv): string { return ''; }

	/**
	 * Worker_Edit_ChangeButton
	 * 
	 * @return string "Update"
	 * @return string "Изменить"
	 */
	public static function Worker_Edit_ChangeButton(...$argv): string { return ''; }

	/**
	 * Worker_Edit_CreateButton
	 * 
	 * @return string "Create"
	 * @return string "Создать"
	 */
	public static function Worker_Edit_CreateButton(...$argv): string { return ''; }

	/**
	 * Worker_Edit_NameLengthError
	 * 
	 * @return string "Name length must be between 2 and 255 characters"
	 * @return string "Название должно быть от 2 до 255 символов"
	 */
	public static function Worker_Edit_NameLengthError(...$argv): string { return ''; }

	/**
	 * Worker_Edit_WorkerNotFound
	 * 
	 * @return string "Worker not found"
	 * @return string "Обработчик не найден"
	 */
	public static function Worker_Edit_WorkerNotFound(...$argv): string { return ''; }

	/**
	 * Worker_Edit_PermissionDenied
	 * 
	 * @return string "You don\'t have permission to edit this worker"
	 * @return string "У вас нет прав на редактирование этого обработчика"
	 */
	public static function Worker_Edit_PermissionDenied(...$argv): string { return ''; }

	/**
	 * Worker_Edit_NotingChanged
	 * 
	 * @return string "Nothing changed"
	 * @return string "Ничего не изменилось"
	 */
	public static function Worker_Edit_NotingChanged(...$argv): string { return ''; }

	/**
	 * Worker_Create_PermissionDenied
	 * 
	 * @return string "You don\'t have permission to create a worker"
	 * @return string "У вас нет прав на создание обработчика"
	 */
	public static function Worker_Create_PermissionDenied(...$argv): string { return ''; }

	/**
	 * Framework_ServerVersion
	 * 
	 * @return string "Server Version"
	 * @return string "Версия сервера"
	 */
	public static function Framework_ServerVersion(...$argv): string { return ''; }

	/**
	 * Framework_Login_Title
	 * 
	 * @return string "Login"
	 * @return string "Авторизация"
	 */
	public static function Framework_Login_Title(...$argv): string { return ''; }

	/**
	 * Framework_Login_Subtitle
	 * 
	 * @return string "Please enter your E-mail and password!"
	 * @return string "Пожалуйста, введите ваш E-mail и пароль!"
	 */
	public static function Framework_Login_Subtitle(...$argv): string { return ''; }

	/**
	 * Framework_Login_Email
	 * 
	 * @return string "Email"
	 * @return string "Email"
	 */
	public static function Framework_Login_Email(...$argv): string { return ''; }

	/**
	 * Framework_Login_Password
	 * 
	 * @return string "Password"
	 * @return string "Пароль"
	 */
	public static function Framework_Login_Password(...$argv): string { return ''; }

	/**
	 * Framework_Login_Submit
	 * 
	 * @return string "Login"
	 * @return string "Войти"
	 */
	public static function Framework_Login_Submit(...$argv): string { return ''; }

	/**
	 * Framework_Login_ForgotPassword
	 * 
	 * @return string "Forgot password?"
	 * @return string "Забыли пароль?"
	 */
	public static function Framework_Login_ForgotPassword(...$argv): string { return ''; }

	/**
	 * Framework_Login_NoAccount
	 * 
	 * @return string "Don\'t have an account?"
	 * @return string "Еще нет аккаунта?"
	 */
	public static function Framework_Login_NoAccount(...$argv): string { return ''; }

	/**
	 * Framework_Login_SignUp
	 * 
	 * @return string "Sign Up"
	 * @return string "Регистрация"
	 */
	public static function Framework_Login_SignUp(...$argv): string { return ''; }

	/**
	 * Framework_Login_InvalidCredentials
	 * 
	 * @return string "Invalid email or password"
	 * @return string "Неверный email или пароль"
	 */
	public static function Framework_Login_InvalidCredentials(...$argv): string { return ''; }

	/**
	 * Framework_Login_SessionExpired
	 * 
	 * @return string "Your session has expired. Please log in again."
	 * @return string "Ваша сессия истекла. Пожалуйста, войдите снова."
	 */
	public static function Framework_Login_SessionExpired(...$argv): string { return ''; }

	/**
	 * Framework_Login_SignIn
	 * 
	 * @return string "Sign In"
	 * @return string "Войти"
	 */
	public static function Framework_Login_SignIn(...$argv): string { return ''; }

	/**
	 * Framework_Login_LoginWith
	 * 
	 * @return string "Sign In with %s"
	 * @return string "Войти через %s"
	 */
	public static function Framework_Login_LoginWith(...$argv): string { return ''; }

	/**
	 * Framework_Login_OpenID
	 * 
	 * @return string "OpenID Connect"
	 * @return string "OpenID Connect"
	 */
	public static function Framework_Login_OpenID(...$argv): string { return ''; }

	/**
	 * Framework_Login_OAuth
	 * 
	 * @return string "OAuth"
	 * @return string "OAuth"
	 */
	public static function Framework_Login_OAuth(...$argv): string { return ''; }

	/**
	 * Framework_Login_TooManyLoginAttempts
	 * 
	 * @return string "Too many login attempts. Please try again later."
	 * @return string "Слишком много попыток входа. Пожалуйста, попробуйте позже."
	 */
	public static function Framework_Login_TooManyLoginAttempts(...$argv): string { return ''; }

	/**
	 * Framework_Login_EmailAndPasswordRequired
	 * 
	 * @return string "Email and Password are required."
	 * @return string "Email и Пароль обязательны для заполнения."
	 */
	public static function Framework_Login_EmailAndPasswordRequired(...$argv): string { return ''; }

	/**
	 * Framework_Login_SignInWithLoginAndPasswordDenied
	 * 
	 * @return string "Sign in with login and password denied"
	 * @return string "Вход с логином и паролем запрещен"
	 */
	public static function Framework_Login_SignInWithLoginAndPasswordDenied(...$argv): string { return ''; }

	/**
	 * Framework_Login_AuthenticateError
	 * 
	 * @return string "Error: Please try again later!"
	 * @return string "Ошибка: Пожалуйста попробуйте позже!"
	 */
	public static function Framework_Login_AuthenticateError(...$argv): string { return ''; }

	/**
	 * Framework_Login_InvalidTOTP
	 * 
	 * @return string "Invalid authentication code!"
	 * @return string "Неверный код аутентификации"
	 */
	public static function Framework_Login_InvalidTOTP(...$argv): string { return ''; }

	/**
	 * Framework_Login_EmailNotVerified
	 * 
	 * @return string "Email not confirmed. Please check your email to confirm your registration or resend the link."
	 * @return string "Email не подтвержден. Проверьте почту для подтверждения регистрации или отправьте ссылку повторно."
	 */
	public static function Framework_Login_EmailNotVerified(...$argv): string { return ''; }

	/**
	 * Framework_Login_ResendEmailVerificationButton
	 * 
	 * @return string "Resend confirmation link"
	 * @return string "Отправить повторную ссылку"
	 */
	public static function Framework_Login_ResendEmailVerificationButton(...$argv): string { return ''; }

	/**
	 * Framework_Login_ResendEmailSuccess
	 * 
	 * @return string "Please check your email for confirmation"
	 * @return string "Проверьте почту для подтверждения регистрации"
	 */
	public static function Framework_Login_ResendEmailSuccess(...$argv): string { return ''; }

	/**
	 * Framework_Login_EmailVerified
	 * 
	 * @return string "Your email has been confirmed"
	 * @return string "Ваш Email подтвержден"
	 */
	public static function Framework_Login_EmailVerified(...$argv): string { return ''; }

	/**
	 * Framework_Login_TwoFactor_Title
	 * 
	 * @return string "Two Factor Authentication"
	 * @return string "Двухфакторная аутентификация"
	 */
	public static function Framework_Login_TwoFactor_Title(...$argv): string { return ''; }

	/**
	 * Framework_Login_TwoFactor_Description
	 * 
	 * @return string "To be able to login you need enter the verification code below."
	 * @return string "Чтобы войти в систему, вам необходимо ввести код подтверждения ниже."
	 */
	public static function Framework_Login_TwoFactor_Description(...$argv): string { return ''; }

	/**
	 * Framework_Login_TwoFactor_EnterCode
	 * 
	 * @return string "Enter verification code"
	 * @return string "Введите проверочный код"
	 */
	public static function Framework_Login_TwoFactor_EnterCode(...$argv): string { return ''; }

	/**
	 * Framework_SignUp_Success
	 * 
	 * @return string "Registration successful. Please check your email for confirmation."
	 * @return string "Регистрация прошла успешно. Проверьте почту для подтверждения регистрации."
	 */
	public static function Framework_SignUp_Success(...$argv): string { return ''; }

	/**
	 * Framework_SignUp_Error
	 * 
	 * @return string "Registration error"
	 * @return string "Ошибка регистрации"
	 */
	public static function Framework_SignUp_Error(...$argv): string { return ''; }

	/**
	 * Framework_SignUp_EmailSubject
	 * 
	 * @return string "Confirmation of registration"
	 * @return string "Подтверждение регистрации"
	 */
	public static function Framework_SignUp_EmailSubject(...$argv): string { return ''; }

	/**
	 * Framework_SignUp_EmailBody
	 * 
	 * @return string "To confirm your registration, please follow the link below: %s"
	 * @return string "Чтобы подтвердить регистрацию, перейдите по следующей ссылке: %s"
	 */
	public static function Framework_SignUp_EmailBody(...$argv): string { return ''; }

	/**
	 * Framework_SignUp_InvalidOrExpiredToken
	 * 
	 * @return string "This link for confirmation of registration is invalid or has expired."
	 * @return string "Эта ссылка для подтверждения регистрации недействительна или устарела."
	 */
	public static function Framework_SignUp_InvalidOrExpiredToken(...$argv): string { return ''; }

	/**
	 * Framework_Forgot_Title
	 * 
	 * @return string "Forgot Password"
	 * @return string "Восстановление пароля"
	 */
	public static function Framework_Forgot_Title(...$argv): string { return ''; }

	/**
	 * Framework_Forgot_Subtitle
	 * 
	 * @return string "Please enter your E-mail"
	 * @return string "Пожалуйста, введите ваш Email"
	 */
	public static function Framework_Forgot_Subtitle(...$argv): string { return ''; }

	/**
	 * Framework_Forgot_RequestButton
	 * 
	 * @return string "Send Reset Link"
	 * @return string "Отправить ссылку для сброса"
	 */
	public static function Framework_Forgot_RequestButton(...$argv): string { return ''; }

	/**
	 * Framework_Forgot_InstructionsSent
	 * 
	 * @return string "If an account with that email exists, we have sent instructions to reset your password."
	 * @return string "Если учётная запись с таким адресом электронной почты существует, мы отправили вам инструкции по сбросу пароля."
	 */
	public static function Framework_Forgot_InstructionsSent(...$argv): string { return ''; }

	/**
	 * Framework_Forgot_ResetTitle
	 * 
	 * @return string "Reset Password"
	 * @return string "Сброс пароля"
	 */
	public static function Framework_Forgot_ResetTitle(...$argv): string { return ''; }

	/**
	 * Framework_Forgot_ResetSubtitle
	 * 
	 * @return string "Please enter your new password."
	 * @return string "Пожалуйста, введите новый пароль."
	 */
	public static function Framework_Forgot_ResetSubtitle(...$argv): string { return ''; }

	/**
	 * Framework_Forgot_ResetButton
	 * 
	 * @return string "Change Password"
	 * @return string "Сменить пароль"
	 */
	public static function Framework_Forgot_ResetButton(...$argv): string { return ''; }

	/**
	 * Framework_Forgot_InvalidOrExpiredToken
	 * 
	 * @return string "This password reset link is invalid or has expired."
	 * @return string "Эта ссылка для сброса пароля недействительна или устарела."
	 */
	public static function Framework_Forgot_InvalidOrExpiredToken(...$argv): string { return ''; }

	/**
	 * Framework_Forgot_BackToLogin
	 * 
	 * @return string "Back to Login"
	 * @return string "Вернуться к входу"
	 */
	public static function Framework_Forgot_BackToLogin(...$argv): string { return ''; }

	/**
	 * Framework_Forgot_EmailSubject
	 * 
	 * @return string "Password Reset Request"
	 * @return string "Запрос на сброс пароля"
	 */
	public static function Framework_Forgot_EmailSubject(...$argv): string { return ''; }

	/**
	 * Framework_Forgot_EmailBody
	 * 
	 * @return string "To reset your password, please click the following link: %s"
	 * @return string "Чтобы сбросить пароль, перейдите по следующей ссылке: %s"
	 */
	public static function Framework_Forgot_EmailBody(...$argv): string { return ''; }

	/**
	 * Framework_SignOut
	 * 
	 * @return string "Sign out"
	 * @return string "Выйти"
	 */
	public static function Framework_SignOut(...$argv): string { return ''; }

	/**
	 * Framework_Profile_Settings
	 * 
	 * @return string "Settings"
	 * @return string "Настройки"
	 */
	public static function Framework_Profile_Settings(...$argv): string { return ''; }

	/**
	 * Framework_Version
	 * 
	 * @return string "Version"
	 * @return string "Версия"
	 */
	public static function Framework_Version(...$argv): string { return ''; }

	/**
	 * Framework_Settings_Title
	 * 
	 * @return string "Settings"
	 * @return string "Настройки"
	 */
	public static function Framework_Settings_Title(...$argv): string { return ''; }

	/**
	 * Framework_Settings_Subtitle
	 * 
	 * @return string "Manage your account settings"
	 * @return string "Управление настройками вашего аккаунта"
	 */
	public static function Framework_Settings_Subtitle(...$argv): string { return ''; }

	/**
	 * Framework_Settings_ChangePassword
	 * 
	 * @return string "Change Password"
	 * @return string "Сменить пароль"
	 */
	public static function Framework_Settings_ChangePassword(...$argv): string { return ''; }

	/**
	 * Framework_Settings_OldPassword
	 * 
	 * @return string "Old Password"
	 * @return string "Старый пароль"
	 */
	public static function Framework_Settings_OldPassword(...$argv): string { return ''; }

	/**
	 * Framework_Settings_NewPassword
	 * 
	 * @return string "New Password"
	 * @return string "Новый пароль"
	 */
	public static function Framework_Settings_NewPassword(...$argv): string { return ''; }

	/**
	 * Framework_Settings_ConfirmNewPassword
	 * 
	 * @return string "Confirm New Password"
	 * @return string "Подтвердите новый пароль"
	 */
	public static function Framework_Settings_ConfirmNewPassword(...$argv): string { return ''; }

	/**
	 * Framework_Settings_Params
	 * 
	 * @return string "Params"
	 * @return string "Параметры"
	 */
	public static function Framework_Settings_Params(...$argv): string { return ''; }

	/**
	 * Framework_Settings_Active
	 * 
	 * @return string "Active"
	 * @return string "Активный"
	 */
	public static function Framework_Settings_Active(...$argv): string { return ''; }

	/**
	 * Framework_Settings_InActive
	 * 
	 * @return string "InActive"
	 * @return string "Неактивный"
	 */
	public static function Framework_Settings_InActive(...$argv): string { return ''; }

	/**
	 * Framework_Settings_Change
	 * 
	 * @return string "Change"
	 * @return string "Сменить"
	 */
	public static function Framework_Settings_Change(...$argv): string { return ''; }

	/**
	 * Framework_Settings_PasswordChangedSuccessfully
	 * 
	 * @return string "Password changed successfully"
	 * @return string "Пароль успешно изменен"
	 */
	public static function Framework_Settings_PasswordChangedSuccessfully(...$argv): string { return ''; }

	/**
	 * Framework_Settings_OldPasswordIsIncorrect
	 * 
	 * @return string "Old password is incorrect"
	 * @return string "Старый пароль неверен"
	 */
	public static function Framework_Settings_OldPasswordIsIncorrect(...$argv): string { return ''; }

	/**
	 * Framework_Settings_NewPasswordsDoNotMatch
	 * 
	 * @return string "New passwords do not match"
	 * @return string "Новые пароли не совпадают"
	 */
	public static function Framework_Settings_NewPasswordsDoNotMatch(...$argv): string { return ''; }

	/**
	 * Framework_Settings_AllFieldsAreRequired
	 * 
	 * @return string "All fields are required"
	 * @return string "Все поля обязательны для заполнения"
	 */
	public static function Framework_Settings_AllFieldsAreRequired(...$argv): string { return ''; }

	/**
	 * Framework_Settings_UserNotFound
	 * 
	 * @return string "User not found"
	 * @return string "Пользователь не найден"
	 */
	public static function Framework_Settings_UserNotFound(...$argv): string { return ''; }

	/**
	 * Framework_Settings_EmailAlreadyInUse
	 * 
	 * @return string "Email is already in use"
	 * @return string "Email уже используется"
	 */
	public static function Framework_Settings_EmailAlreadyInUse(...$argv): string { return ''; }

	/**
	 * Framework_Settings_ProfileUpdatedSuccessfully
	 * 
	 * @return string "Profile updated successfully"
	 * @return string "Профиль успешно обновлен"
	 */
	public static function Framework_Settings_ProfileUpdatedSuccessfully(...$argv): string { return ''; }

	/**
	 * Framework_Settings_InvalidEmailFormat
	 * 
	 * @return string "Invalid email format"
	 * @return string "Неверный формат email"
	 */
	public static function Framework_Settings_InvalidEmailFormat(...$argv): string { return ''; }

	/**
	 * Framework_Settings_NameLengthError
	 * 
	 * @return string "Name length must be between 2 and 64 characters"
	 * @return string "Имя должно быть от 2 до 64 символов"
	 */
	public static function Framework_Settings_NameLengthError(...$argv): string { return ''; }

	/**
	 * Framework_Settings_SurnameLengthError
	 * 
	 * @return string "Surname length must be between 2 and 64 characters"
	 * @return string "Фамилия должна быть от 2 до 64 символов"
	 */
	public static function Framework_Settings_SurnameLengthError(...$argv): string { return ''; }

	/**
	 * Framework_Settings_EmailLengthError
	 * 
	 * @return string "Email length must be between 6 and 128 characters"
	 * @return string "Email должен быть от 6 до 128 символов"
	 */
	public static function Framework_Settings_EmailLengthError(...$argv): string { return ''; }

	/**
	 * Framework_Settings_RegisterTime
	 * 
	 * @return string "Register Time"
	 * @return string "Дата регистрации"
	 */
	public static function Framework_Settings_RegisterTime(...$argv): string { return ''; }

	/**
	 * Framework_Settings_LoginTime
	 * 
	 * @return string "Login Time"
	 * @return string "Дата входа"
	 */
	public static function Framework_Settings_LoginTime(...$argv): string { return ''; }

	/**
	 * Framework_Settings_UpdateTime
	 * 
	 * @return string "Update Time"
	 * @return string "Дата обновления"
	 */
	public static function Framework_Settings_UpdateTime(...$argv): string { return ''; }

	/**
	 * Framework_Settings_NotingChanged
	 * 
	 * @return string "Nothing changed"
	 * @return string "Нет изменений"
	 */
	public static function Framework_Settings_NotingChanged(...$argv): string { return ''; }

	/**
	 * Framework_Settings_PasswordRequired
	 * 
	 * @return string "Password is required"
	 * @return string "Пароль обязателен для заполнения"
	 */
	public static function Framework_Settings_PasswordRequired(...$argv): string { return ''; }

	/**
	 * Framework_Settings_UserProfile_Title
	 * 
	 * @return string "User Profile"
	 * @return string "Профиль пользователя"
	 */
	public static function Framework_Settings_UserProfile_Title(...$argv): string { return ''; }

	/**
	 * Framework_Settings_UserProfile_Id
	 * 
	 * @return string "User ID"
	 * @return string "ID пользователя"
	 */
	public static function Framework_Settings_UserProfile_Id(...$argv): string { return ''; }

	/**
	 * Framework_Settings_UserProfile_Name
	 * 
	 * @return string "Name"
	 * @return string "Имя"
	 */
	public static function Framework_Settings_UserProfile_Name(...$argv): string { return ''; }

	/**
	 * Framework_Settings_UserProfile_Surname
	 * 
	 * @return string "Surname"
	 * @return string "Фамилия"
	 */
	public static function Framework_Settings_UserProfile_Surname(...$argv): string { return ''; }

	/**
	 * Framework_Settings_UserProfile_Email
	 * 
	 * @return string "Email"
	 * @return string "Email"
	 */
	public static function Framework_Settings_UserProfile_Email(...$argv): string { return ''; }

	/**
	 * Framework_Settings_UserProfile_TelegramId
	 * 
	 * @return string "Telegram ID"
	 * @return string "Telegram ID"
	 */
	public static function Framework_Settings_UserProfile_TelegramId(...$argv): string { return ''; }

	/**
	 * Framework_Settings_UserProfile_Change
	 * 
	 * @return string "Change Profile"
	 * @return string "Изменить профиль"
	 */
	public static function Framework_Settings_UserProfile_Change(...$argv): string { return ''; }

	/**
	 * Framework_Settings_UserProfile_Create
	 * 
	 * @return string "Create User"
	 * @return string "Создать пользователя"
	 */
	public static function Framework_Settings_UserProfile_Create(...$argv): string { return ''; }

	/**
	 * Framework_Settings_TwoFactor_Title
	 * 
	 * @return string "Two Factor Authentication"
	 * @return string "Двухфакторная аутентификация"
	 */
	public static function Framework_Settings_TwoFactor_Title(...$argv): string { return ''; }

	/**
	 * Framework_Settings_TwoFactor_Header
	 * 
	 * @return string "Set up two-factor authentication"
	 * @return string "Настройте двухфакторную аутентификацию"
	 */
	public static function Framework_Settings_TwoFactor_Header(...$argv): string { return ''; }

	/**
	 * Framework_Settings_TwoFactor_Description
	 * 
	 * @return string "To set up two-factor authentication, you need to scan this QR code using the Google authentication app and enter the verification code below."
	 * @return string "Чтобы установить двухфакторную аутентификацию, вам необходимо отсканировать этот QR-код с помощью приложения аутентификации Google и ввести код подтверждения ниже."
	 */
	public static function Framework_Settings_TwoFactor_Description(...$argv): string { return ''; }

	/**
	 * Framework_Settings_TwoFactor_Enable
	 * 
	 * @return string "Enable"
	 * @return string "Включить"
	 */
	public static function Framework_Settings_TwoFactor_Enable(...$argv): string { return ''; }

	/**
	 * Framework_Settings_TwoFactor_Disable
	 * 
	 * @return string "Disable"
	 * @return string "Выключить"
	 */
	public static function Framework_Settings_TwoFactor_Disable(...$argv): string { return ''; }

	/**
	 * Framework_Settings_TwoFactor_Code
	 * 
	 * @return string "Code"
	 * @return string ""
	 */
	public static function Framework_Settings_TwoFactor_Code(...$argv): string { return ''; }

	/**
	 * Framework_Settings_TwoFactor_EnterCode
	 * 
	 * @return string "Enter verification code"
	 * @return string "Введите проверочный код"
	 */
	public static function Framework_Settings_TwoFactor_EnterCode(...$argv): string { return ''; }

	/**
	 * Framework_Settings_TwoFactor_ErrorSetup
	 * 
	 * @return string "Failed to set up two-factor authentication"
	 * @return string "Не удалось настроить двухфакторную аутентификацию"
	 */
	public static function Framework_Settings_TwoFactor_ErrorSetup(...$argv): string { return ''; }

	/**
	 * Framework_Telegram_SuccessfullySentMessage
	 * 
	 * @return string "Successfully sent message"
	 * @return string "Сообщение успешно отправлено"
	 */
	public static function Framework_Telegram_SuccessfullySentMessage(...$argv): string { return ''; }

	/**
	 * Framework_Telegram_ErrorSendingMessage
	 * 
	 * @return string "Error sending message"
	 * @return string "Ошибка отправки сообщения"
	 */
	public static function Framework_Telegram_ErrorSendingMessage(...$argv): string { return ''; }

	/**
	 * Framework_Telegram_TelegramIdIsRequired
	 * 
	 * @return string "Telegram ID is required"
	 * @return string "Telegram ID необходим для отправки сообщений"
	 */
	public static function Framework_Telegram_TelegramIdIsRequired(...$argv): string { return ''; }

	/**
	 * Framework_Telegram_TelegramBotTokenIsRequired
	 * 
	 * @return string "Telegram Bot Token is required"
	 * @return string "Telegram Bot Token необходим для отправки сообщений"
	 */
	public static function Framework_Telegram_TelegramBotTokenIsRequired(...$argv): string { return ''; }

	/**
	 * Framework_Notifications_Title
	 * 
	 * @return string "Notifications"
	 * @return string "Уведомления"
	 */
	public static function Framework_Notifications_Title(...$argv): string { return ''; }

	/**
	 * Framework_Common_Cancel
	 * 
	 * @return string "Cancel"
	 * @return string "Отмена"
	 */
	public static function Framework_Common_Cancel(...$argv): string { return ''; }

	/**
	 * Framework_Common_Delete
	 * 
	 * @return string "Delete"
	 * @return string "Удалить"
	 */
	public static function Framework_Common_Delete(...$argv): string { return ''; }

	/**
	 * Framework_Common_Create
	 * 
	 * @return string "Create"
	 * @return string "Создать"
	 */
	public static function Framework_Common_Create(...$argv): string { return ''; }

	/**
	 * Framework_Common_Edit
	 * 
	 * @return string "Edit"
	 * @return string "Редактировать"
	 */
	public static function Framework_Common_Edit(...$argv): string { return ''; }

	/**
	 * Framework_Common_Save
	 * 
	 * @return string "Save"
	 * @return string "Сохранить"
	 */
	public static function Framework_Common_Save(...$argv): string { return ''; }

	/**
	 * Framework_Common_Search
	 * 
	 * @return string "Search"
	 * @return string "Поиск"
	 */
	public static function Framework_Common_Search(...$argv): string { return ''; }

	/**
	 * Framework_Common_Reset
	 * 
	 * @return string "Reset"
	 * @return string "Сбросить"
	 */
	public static function Framework_Common_Reset(...$argv): string { return ''; }

	/**
	 * Framework_Common_Filter
	 * 
	 * @return string "Filter"
	 * @return string "Фильтр"
	 */
	public static function Framework_Common_Filter(...$argv): string { return ''; }

	/**
	 * Framework_Common_Sort
	 * 
	 * @return string "Sort"
	 * @return string "Сортировать"
	 */
	public static function Framework_Common_Sort(...$argv): string { return ''; }

	/**
	 * Framework_Common_Close
	 * 
	 * @return string "Close"
	 * @return string "Закрыть"
	 */
	public static function Framework_Common_Close(...$argv): string { return ''; }

	/**
	 * Framework_Common_Add
	 * 
	 * @return string "Add"
	 * @return string "Добавить"
	 */
	public static function Framework_Common_Add(...$argv): string { return ''; }

	/**
	 * Framework_Common_Remove
	 * 
	 * @return string "Remove"
	 * @return string "Удалить"
	 */
	public static function Framework_Common_Remove(...$argv): string { return ''; }

	/**
	 * Framework_Common_FormLooksGood
	 * 
	 * @return string "Looks good!"
	 * @return string "Отлично!"
	 */
	public static function Framework_Common_FormLooksGood(...$argv): string { return ''; }

	/**
	 * Framework_Common_FormRequired
	 * 
	 * @return string "Please fill out this field."
	 * @return string "Пожалуйста, заполните это поле"
	 */
	public static function Framework_Common_FormRequired(...$argv): string { return ''; }

	/**
	 * Framework_Common_FormPasswordEquals
	 * 
	 * @return string "Passwords must match"
	 * @return string "Пароли должны совпадать"
	 */
	public static function Framework_Common_FormPasswordEquals(...$argv): string { return ''; }

	/**
	 * Framework_Common_CreateTime
	 * 
	 * @return string "Create Time"
	 * @return string "Дата создания"
	 */
	public static function Framework_Common_CreateTime(...$argv): string { return ''; }

	/**
	 * Framework_Common_UpdateTime
	 * 
	 * @return string "Update Time"
	 * @return string "Дата обновления"
	 */
	public static function Framework_Common_UpdateTime(...$argv): string { return ''; }

	/**
	 * Framework_Common_RegisterTime
	 * 
	 * @return string "Register Time"
	 * @return string "Дата регистрации"
	 */
	public static function Framework_Common_RegisterTime(...$argv): string { return ''; }

	/**
	 * Framework_Common_LoginTime
	 * 
	 * @return string "Login Time"
	 * @return string "Дата входа"
	 */
	public static function Framework_Common_LoginTime(...$argv): string { return ''; }

	/**
	 * Framework_Menu_Customers
	 * 
	 * @return string "Customers"
	 * @return string "Клиенты"
	 */
	public static function Framework_Menu_Customers(...$argv): string { return ''; }

	/**
	 * Framework_Menu_Products
	 * 
	 * @return string "Products"
	 * @return string "Продукты"
	 */
	public static function Framework_Menu_Products(...$argv): string { return ''; }

	/**
	 * Framework_Menu_Admin
	 * 
	 * @return string "Admin"
	 * @return string "Admin"
	 */
	public static function Framework_Menu_Admin(...$argv): string { return ''; }

	/**
	 * Framework_Menu_Users
	 * 
	 * @return string "Users"
	 * @return string "Пользователи"
	 */
	public static function Framework_Menu_Users(...$argv): string { return ''; }

	/**
	 * Framework_Menu_Groups
	 * 
	 * @return string "Groups"
	 * @return string "Группы"
	 */
	public static function Framework_Menu_Groups(...$argv): string { return ''; }

	/**
	 * Framework_Menu_Logs
	 * 
	 * @return string "Logs"
	 * @return string "Логи"
	 */
	public static function Framework_Menu_Logs(...$argv): string { return ''; }

	/**
	 * Framework_Menu_User
	 * 
	 * @return string "User"
	 * @return string "Пользователь"
	 */
	public static function Framework_Menu_User(...$argv): string { return ''; }

	/**
	 * Framework_Menu_Group
	 * 
	 * @return string "Group"
	 * @return string "Группа"
	 */
	public static function Framework_Menu_Group(...$argv): string { return ''; }

	/**
	 * Framework_Menu_Subject
	 * 
	 * @return string "Subject"
	 * @return string "Субъект"
	 */
	public static function Framework_Menu_Subject(...$argv): string { return ''; }

	/**
	 * Framework_Menu_GroupPermissions
	 * 
	 * @return string "Permissions for group &laquo;%s&raquo; id=%d"
	 * @return string "Права доступа для группы &laquo;%s&raquo; id=%d"
	 */
	public static function Framework_Menu_GroupPermissions(...$argv): string { return ''; }

	/**
	 * Framework_Menu_UserPermissions
	 * 
	 * @return string "Permissions for user &laquo;%s %s&raquo; id=%d"
	 * @return string "Права доступа для пользователя &laquo;%s %s&raquo; id=%d"
	 */
	public static function Framework_Menu_UserPermissions(...$argv): string { return ''; }

	/**
	 * Framework_Menu_UserGroups
	 * 
	 * @return string "Groups for user &laquo;%s %s&raquo; id=%d"
	 * @return string "Группы для пользователя &laquo;%s %s&raquo; id=%d"
	 */
	public static function Framework_Menu_UserGroups(...$argv): string { return ''; }

	/**
	 * Framework_Menu_CreateUser
	 * 
	 * @return string "Create User"
	 * @return string "Создание пользователя"
	 */
	public static function Framework_Menu_CreateUser(...$argv): string { return ''; }

	/**
	 * Framework_Menu_EditUser
	 * 
	 * @return string "Edit User &laquo;%s&raquo; id=%d"
	 * @return string "Редактирование пользователя &laquo;%s&raquo; id=%d"
	 */
	public static function Framework_Menu_EditUser(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_Admin
	 * 
	 * @return string "Admin"
	 * @return string "Admin"
	 */
	public static function Framework_Permissions_Admin(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_ManageUsers
	 * 
	 * @return string "Manage Users"
	 * @return string "Управление пользователями"
	 */
	public static function Framework_Permissions_ManageUsers(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_ManageUser
	 * 
	 * @return string "Manage User"
	 * @return string "Управление пользователем"
	 */
	public static function Framework_Permissions_ManageUser(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_CreateUser
	 * 
	 * @return string "Create User"
	 * @return string "Создание пользователя"
	 */
	public static function Framework_Permissions_CreateUser(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_ManageUserPermissions
	 * 
	 * @return string "Manage User Permissions"
	 * @return string "Управление правами пользователя"
	 */
	public static function Framework_Permissions_ManageUserPermissions(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_ManageUserGroups
	 * 
	 * @return string "Manage User Groups"
	 * @return string "Управление группами пользователя"
	 */
	public static function Framework_Permissions_ManageUserGroups(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_ManageGroups
	 * 
	 * @return string "Manage Groups"
	 * @return string "Управление группами"
	 */
	public static function Framework_Permissions_ManageGroups(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_ManageGroup
	 * 
	 * @return string "Manage Group"
	 * @return string "Управление группой"
	 */
	public static function Framework_Permissions_ManageGroup(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_CreateGroup
	 * 
	 * @return string "Create Group"
	 * @return string "Создание группы"
	 */
	public static function Framework_Permissions_CreateGroup(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_ManageGroupPermissions
	 * 
	 * @return string "Manage Group Permissions"
	 * @return string "Управление правами группы"
	 */
	public static function Framework_Permissions_ManageGroupPermissions(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_ImpersonateUser
	 * 
	 * @return string "Impersonate Users"
	 * @return string "Вход под пользователем"
	 */
	public static function Framework_Permissions_ImpersonateUser(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_Group
	 * 
	 * @return string "Group"
	 * @return string "Группа"
	 */
	public static function Framework_Permissions_Group(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_SubjectTypes_Group
	 * 
	 * @return string "Group"
	 * @return string "Группа"
	 */
	public static function Framework_Permissions_SubjectTypes_Group(...$argv): string { return ''; }

	/**
	 * Framework_Permissions_SubjectTypes_User
	 * 
	 * @return string "User"
	 * @return string "Пользователь"
	 */
	public static function Framework_Permissions_SubjectTypes_User(...$argv): string { return ''; }

	/**
	 * Framework_Permission_Table_Title
	 * 
	 * @return string "Permission"
	 * @return string "Права доступа"
	 */
	public static function Framework_Permission_Table_Title(...$argv): string { return ''; }

	/**
	 * Framework_Permission_Table_Read
	 * 
	 * @return string "Read"
	 * @return string "Чтение"
	 */
	public static function Framework_Permission_Table_Read(...$argv): string { return ''; }

	/**
	 * Framework_Permission_Table_Write
	 * 
	 * @return string "Write"
	 * @return string "Запись"
	 */
	public static function Framework_Permission_Table_Write(...$argv): string { return ''; }

	/**
	 * Framework_Permission_Table_Delete
	 * 
	 * @return string "Delete"
	 * @return string "Удаление"
	 */
	public static function Framework_Permission_Table_Delete(...$argv): string { return ''; }

	/**
	 * Framework_Permission_Table_AccessRead
	 * 
	 * @return string "Read access"
	 * @return string "Чтение прав"
	 */
	public static function Framework_Permission_Table_AccessRead(...$argv): string { return ''; }

	/**
	 * Framework_Permission_Table_AccessWrite
	 * 
	 * @return string "Write access"
	 * @return string "Запись прав"
	 */
	public static function Framework_Permission_Table_AccessWrite(...$argv): string { return ''; }

	/**
	 * Framework_Permission_Table_AccessChange
	 * 
	 * @return string "Change access"
	 * @return string "Изменение прав"
	 */
	public static function Framework_Permission_Table_AccessChange(...$argv): string { return ''; }

	/**
	 * Framework_Permission_access_0
	 * 
	 * @return string "Default No or from groups"
	 * @return string ""
	 */
	public static function Framework_Permission_access_0(...$argv): string { return ''; }

	/**
	 * Framework_Permission_access_1
	 * 
	 * @return string "Yes"
	 * @return string ""
	 */
	public static function Framework_Permission_access_1(...$argv): string { return ''; }

	/**
	 * Framework_Permission_access_2
	 * 
	 * @return string "No"
	 * @return string ""
	 */
	public static function Framework_Permission_access_2(...$argv): string { return ''; }

	/**
	 * Framework_Permission_access_3
	 * 
	 * @return string "По умолчанию НЕТ или от группы"
	 * @return string ""
	 */
	public static function Framework_Permission_access_3(...$argv): string { return ''; }

	/**
	 * Framework_Permission_access_4
	 * 
	 * @return string "ДА"
	 * @return string ""
	 */
	public static function Framework_Permission_access_4(...$argv): string { return ''; }

	/**
	 * Framework_Permission_access_5
	 * 
	 * @return string "НЕТ"
	 * @return string ""
	 */
	public static function Framework_Permission_access_5(...$argv): string { return ''; }

	/**
	 * Framework_Permission_FromGroups
	 * 
	 * @return string "From Groups"
	 * @return string "Из групп"
	 */
	public static function Framework_Permission_FromGroups(...$argv): string { return ''; }

	/**
	 * Framework_Permission_ModalTitle
	 * 
	 * @return string "Permission"
	 * @return string "Права доступа"
	 */
	public static function Framework_Permission_ModalTitle(...$argv): string { return ''; }

	/**
	 * Framework_Permission_ModalType
	 * 
	 * @return string "Type"
	 * @return string "Тип"
	 */
	public static function Framework_Permission_ModalType(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Title
	 * 
	 * @return string "Groups"
	 * @return string "Группы"
	 */
	public static function Framework_Groups_Title(...$argv): string { return ''; }

	/**
	 * Framework_Groups_ModalTitle
	 * 
	 * @return string "Group"
	 * @return string "Группа"
	 */
	public static function Framework_Groups_ModalTitle(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Subtitle
	 * 
	 * @return string "Manage Groups"
	 * @return string "Управление группами"
	 */
	public static function Framework_Groups_Subtitle(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Table_Title
	 * 
	 * @return string "Title"
	 * @return string "Название"
	 */
	public static function Framework_Groups_Table_Title(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Table_UsersCount
	 * 
	 * @return string "Users Count"
	 * @return string "Кол-во пользователей"
	 */
	public static function Framework_Groups_Table_UsersCount(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Table_Permissions
	 * 
	 * @return string "Permissions"
	 * @return string "Доступы"
	 */
	public static function Framework_Groups_Table_Permissions(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Table_Delete
	 * 
	 * @return string "Delete"
	 * @return string "Удалить"
	 */
	public static function Framework_Groups_Table_Delete(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Table_Actions
	 * 
	 * @return string "Actions"
	 * @return string "Действия"
	 */
	public static function Framework_Groups_Table_Actions(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Delete_Title
	 * 
	 * @return string "Removing the group"
	 * @return string "Удаление группы"
	 */
	public static function Framework_Groups_Delete_Title(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Delete_Confirmation
	 * 
	 * @return string "Are you sure you want to delete the group {group}?"
	 * @return string "Вы уверены, что хотите удалить группу {group}?"
	 */
	public static function Framework_Groups_Delete_Confirmation(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Delete_GroupNotFound
	 * 
	 * @return string "Group not found"
	 * @return string "Группа не найдена"
	 */
	public static function Framework_Groups_Delete_GroupNotFound(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Delete_PermissionDenied
	 * 
	 * @return string "You don\'t have permission to delete this group"
	 * @return string "У вас нет прав на удаление этой группы"
	 */
	public static function Framework_Groups_Delete_PermissionDenied(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Delete_DefaultGroupDenied
	 * 
	 * @return string "You can\'t delete the &laquo;Default&raquo; group"
	 * @return string "Вы не можете удалить группу &laquo;Default&raquo;"
	 */
	public static function Framework_Groups_Delete_DefaultGroupDenied(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Delete_AdminGroupDenied
	 * 
	 * @return string "You can\'t delete the &laquo;Admin&raquo; group"
	 * @return string "Вы не можете удалить группу &laquo;Admin&raquo;"
	 */
	public static function Framework_Groups_Delete_AdminGroupDenied(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Create_Id
	 * 
	 * @return string "ID"
	 * @return string "ID"
	 */
	public static function Framework_Groups_Create_Id(...$argv): string { return ''; }

	/**
	 * Framework_Groups_Create_Title
	 * 
	 * @return string "Title"
	 * @return string "Название"
	 */
	public static function Framework_Groups_Create_Title(...$argv): string { return ''; }

	/**
	 * Framework_Users_Table_Name
	 * 
	 * @return string "Name"
	 * @return string "Имя"
	 */
	public static function Framework_Users_Table_Name(...$argv): string { return ''; }

	/**
	 * Framework_Users_Table_Surname
	 * 
	 * @return string "Surname"
	 * @return string "Фамилия"
	 */
	public static function Framework_Users_Table_Surname(...$argv): string { return ''; }

	/**
	 * Framework_Users_Table_Email
	 * 
	 * @return string "Email"
	 * @return string "Email"
	 */
	public static function Framework_Users_Table_Email(...$argv): string { return ''; }

	/**
	 * Framework_Users_Table_Groups
	 * 
	 * @return string "Groups"
	 * @return string "Группы"
	 */
	public static function Framework_Users_Table_Groups(...$argv): string { return ''; }

	/**
	 * Framework_Users_Table_Actions
	 * 
	 * @return string "Actions"
	 * @return string "Действия"
	 */
	public static function Framework_Users_Table_Actions(...$argv): string { return ''; }

	/**
	 * Framework_Users_Table_Permissions
	 * 
	 * @return string "Permissions"
	 * @return string "Доступы"
	 */
	public static function Framework_Users_Table_Permissions(...$argv): string { return ''; }

	/**
	 * Framework_Users_Table_Delete
	 * 
	 * @return string "Delete"
	 * @return string "Удалить"
	 */
	public static function Framework_Users_Table_Delete(...$argv): string { return ''; }

	/**
	 * Framework_Users_Table_Status
	 * 
	 * @return string "Status"
	 * @return string "Статус"
	 */
	public static function Framework_Users_Table_Status(...$argv): string { return ''; }

	/**
	 * Framework_Users_Table_ImpersonateUser
	 * 
	 * @return string "Impersonate User"
	 * @return string "Вход под пользователем"
	 */
	public static function Framework_Users_Table_ImpersonateUser(...$argv): string { return ''; }

	/**
	 * Framework_Users_Delete_Title
	 * 
	 * @return string "Removing user"
	 * @return string "Удаление пользователя"
	 */
	public static function Framework_Users_Delete_Title(...$argv): string { return ''; }

	/**
	 * Framework_Users_Delete_Confirmation
	 * 
	 * @return string "Are you sure you want to delete the user {user}?"
	 * @return string "Вы уверены, что хотите удалить пользователя {user}?"
	 */
	public static function Framework_Users_Delete_Confirmation(...$argv): string { return ''; }

	/**
	 * Framework_Users_Delete_LastAdminDenied
	 * 
	 * @return string "Cannot delete the last user in &laquo;Admin&raquo; group"
	 * @return string "Нельзя удалять последнего пользователя в группе &laquo;Admin&raquo;"
	 */
	public static function Framework_Users_Delete_LastAdminDenied(...$argv): string { return ''; }

	/**
	 * Framework_Users_Delete_SelfDenied
	 * 
	 * @return string "Cannot delete yourself"
	 * @return string "Нельзя удалить себя"
	 */
	public static function Framework_Users_Delete_SelfDenied(...$argv): string { return ''; }

	/**
	 * Framework_Users_Delete_UserNotFound
	 * 
	 * @return string "User not found"
	 * @return string "Пользователь не найден"
	 */
	public static function Framework_Users_Delete_UserNotFound(...$argv): string { return ''; }

	/**
	 * Framework_Users_Flags_NeedChangePassword
	 * 
	 * @return string "Password change required"
	 * @return string "Требуется изменение пароля"
	 */
	public static function Framework_Users_Flags_NeedChangePassword(...$argv): string { return ''; }

	/**
	 * Framework_Errors_PermissionDenied
	 * 
	 * @return string "You don\'t have permission"
	 * @return string "У вас нет прав"
	 */
	public static function Framework_Errors_PermissionDenied(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Actions_Other
	 * 
	 * @return string "Other"
	 * @return string "Разное"
	 */
	public static function Framework_Logs_Actions_Other(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Actions_Create
	 * 
	 * @return string "Create"
	 * @return string "Создание"
	 */
	public static function Framework_Logs_Actions_Create(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Actions_Update
	 * 
	 * @return string "Update"
	 * @return string "Обновление"
	 */
	public static function Framework_Logs_Actions_Update(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Actions_Delete
	 * 
	 * @return string "Delete"
	 * @return string "Удаление"
	 */
	public static function Framework_Logs_Actions_Delete(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Actions_Login
	 * 
	 * @return string "Login"
	 * @return string "Вход"
	 */
	public static function Framework_Logs_Actions_Login(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Actions_Logout
	 * 
	 * @return string "Logout"
	 * @return string "Выход"
	 */
	public static function Framework_Logs_Actions_Logout(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Actions_StartImpersonate
	 * 
	 * @return string "Start impersonate user"
	 * @return string "Вход под пользователем"
	 */
	public static function Framework_Logs_Actions_StartImpersonate(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Actions_StopImpersonate
	 * 
	 * @return string "Stop impersonate user"
	 * @return string "Выход из под пользователя"
	 */
	public static function Framework_Logs_Actions_StopImpersonate(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Report_User
	 * 
	 * @return string "User"
	 * @return string "Пользователь"
	 */
	public static function Framework_Logs_Report_User(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Report_Code
	 * 
	 * @return string "Code"
	 * @return string "Код"
	 */
	public static function Framework_Logs_Report_Code(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Report_Action
	 * 
	 * @return string "Action"
	 * @return string "Действие"
	 */
	public static function Framework_Logs_Report_Action(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Report_Object
	 * 
	 * @return string "Object"
	 * @return string "Объект"
	 */
	public static function Framework_Logs_Report_Object(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Report_Data
	 * 
	 * @return string "Data"
	 * @return string "Данные"
	 */
	public static function Framework_Logs_Report_Data(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Report_Time
	 * 
	 * @return string "Date"
	 * @return string "Время"
	 */
	public static function Framework_Logs_Report_Time(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Report_Trace
	 * 
	 * @return string "Trace"
	 * @return string "Trace"
	 */
	public static function Framework_Logs_Report_Trace(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Report_Group
	 * 
	 * @return string "Группа"
	 * @return string ""
	 */
	public static function Framework_Logs_Report_Group(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Filter_Group
	 * 
	 * @return string "Group"
	 * @return string ""
	 */
	public static function Framework_Logs_Filter_Group(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Filter_User
	 * 
	 * @return string "User"
	 * @return string "Пользователь"
	 */
	public static function Framework_Logs_Filter_User(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Filter_Date
	 * 
	 * @return string "Date"
	 * @return string "Дата"
	 */
	public static function Framework_Logs_Filter_Date(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Filter_Code
	 * 
	 * @return string "Code"
	 * @return string "Код"
	 */
	public static function Framework_Logs_Filter_Code(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Filter_Action
	 * 
	 * @return string "Action"
	 * @return string "Действие"
	 */
	public static function Framework_Logs_Filter_Action(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Filter_Object
	 * 
	 * @return string "Object"
	 * @return string "Объект"
	 */
	public static function Framework_Logs_Filter_Object(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Filter_ObjectId
	 * 
	 * @return string "Object ID"
	 * @return string "ID объекта"
	 */
	public static function Framework_Logs_Filter_ObjectId(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Filter_Reset
	 * 
	 * @return string "Reset"
	 * @return string "Сбросить"
	 */
	public static function Framework_Logs_Filter_Reset(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Filter_Apply
	 * 
	 * @return string "Apply"
	 * @return string "Применить"
	 */
	public static function Framework_Logs_Filter_Apply(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Objects_Group
	 * 
	 * @return string "Group"
	 * @return string "Группа"
	 */
	public static function Framework_Logs_Objects_Group(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Objects_ObjectPermissions
	 * 
	 * @return string "Object permissions"
	 * @return string "Права доступа"
	 */
	public static function Framework_Logs_Objects_ObjectPermissions(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Objects_Session
	 * 
	 * @return string "Session"
	 * @return string "Сессия"
	 */
	public static function Framework_Logs_Objects_Session(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Objects_UserGroup
	 * 
	 * @return string "User group"
	 * @return string "Группа пользователя"
	 */
	public static function Framework_Logs_Objects_UserGroup(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Objects_User
	 * 
	 * @return string "User"
	 * @return string "Пользователь"
	 */
	public static function Framework_Logs_Objects_User(...$argv): string { return ''; }

	/**
	 * Framework_Logs_Objects_Notification
	 * 
	 * @return string "Notification"
	 * @return string "Уведомление"
	 */
	public static function Framework_Logs_Objects_Notification(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_today
	 * 
	 * @return string "Go to today"
	 * @return string "Перейти сегодня"
	 */
	public static function Framework_DateTimePicker_today(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_clear
	 * 
	 * @return string "Clear selection"
	 * @return string "Очистить выделение"
	 */
	public static function Framework_DateTimePicker_clear(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_close
	 * 
	 * @return string "Close the picker"
	 * @return string "Закрыть сборщик"
	 */
	public static function Framework_DateTimePicker_close(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_selectMonth
	 * 
	 * @return string "Select Month"
	 * @return string "Выбрать месяц"
	 */
	public static function Framework_DateTimePicker_selectMonth(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_previousMonth
	 * 
	 * @return string "Previous Month"
	 * @return string "Предыдущий месяц"
	 */
	public static function Framework_DateTimePicker_previousMonth(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_nextMonth
	 * 
	 * @return string "Next Month"
	 * @return string "В следующем месяце"
	 */
	public static function Framework_DateTimePicker_nextMonth(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_selectYear
	 * 
	 * @return string "Select Year"
	 * @return string "Выбрать год"
	 */
	public static function Framework_DateTimePicker_selectYear(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_previousYear
	 * 
	 * @return string "Previous Year"
	 * @return string "Предыдущий год"
	 */
	public static function Framework_DateTimePicker_previousYear(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_nextYear
	 * 
	 * @return string "Next Year"
	 * @return string "В следующем году"
	 */
	public static function Framework_DateTimePicker_nextYear(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_selectDecade
	 * 
	 * @return string "Select Decade"
	 * @return string "Выбрать десятилетие"
	 */
	public static function Framework_DateTimePicker_selectDecade(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_previousDecade
	 * 
	 * @return string "Previous Decade"
	 * @return string "Предыдущее десятилетие"
	 */
	public static function Framework_DateTimePicker_previousDecade(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_nextDecade
	 * 
	 * @return string "Next Decade"
	 * @return string "Следующее десятилетие"
	 */
	public static function Framework_DateTimePicker_nextDecade(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_previousCentury
	 * 
	 * @return string "Previous Century"
	 * @return string "Предыдущий век"
	 */
	public static function Framework_DateTimePicker_previousCentury(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_nextCentury
	 * 
	 * @return string "Next Century"
	 * @return string "Следующий век"
	 */
	public static function Framework_DateTimePicker_nextCentury(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_pickHour
	 * 
	 * @return string "Pick Hour"
	 * @return string "Выберите час"
	 */
	public static function Framework_DateTimePicker_pickHour(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_incrementHour
	 * 
	 * @return string "Increment Hour"
	 * @return string "Время увеличения"
	 */
	public static function Framework_DateTimePicker_incrementHour(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_decrementHour
	 * 
	 * @return string "Decrement Hour"
	 * @return string "Уменьшить час"
	 */
	public static function Framework_DateTimePicker_decrementHour(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_pickMinute
	 * 
	 * @return string "Pick Minute"
	 * @return string "Выбрать минуту"
	 */
	public static function Framework_DateTimePicker_pickMinute(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_incrementMinute
	 * 
	 * @return string "Increment Minute"
	 * @return string "Минута приращения"
	 */
	public static function Framework_DateTimePicker_incrementMinute(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_decrementMinute
	 * 
	 * @return string "Decrement Minute"
	 * @return string "Уменьшить минуту"
	 */
	public static function Framework_DateTimePicker_decrementMinute(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_pickSecond
	 * 
	 * @return string "Pick Second"
	 * @return string "Выбрать второй"
	 */
	public static function Framework_DateTimePicker_pickSecond(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_incrementSecond
	 * 
	 * @return string "Increment Second"
	 * @return string "Увеличение секунды"
	 */
	public static function Framework_DateTimePicker_incrementSecond(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_decrementSecond
	 * 
	 * @return string "Decrement Second"
	 * @return string "Уменьшение секунды"
	 */
	public static function Framework_DateTimePicker_decrementSecond(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_toggleMeridiem
	 * 
	 * @return string "Toggle Meridiem"
	 * @return string "Переключить период"
	 */
	public static function Framework_DateTimePicker_toggleMeridiem(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_selectTime
	 * 
	 * @return string "Select Time"
	 * @return string "Выбрать время"
	 */
	public static function Framework_DateTimePicker_selectTime(...$argv): string { return ''; }

	/**
	 * Framework_DateTimePicker_selectDate
	 * 
	 * @return string "Select Date"
	 * @return string "Выбрать дату"
	 */
	public static function Framework_DateTimePicker_selectDate(...$argv): string { return ''; }

	/**
	 * Framework_Help
	 * 
	 * @return string "Help"
	 * @return string "Помощь"
	 */
	public static function Framework_Help(...$argv): string { return ''; }

	/**
	 * Framework_January
	 * 
	 * @return string "January"
	 * @return string "Январь"
	 */
	public static function Framework_January(...$argv): string { return ''; }

	/**
	 * Framework_February
	 * 
	 * @return string "February"
	 * @return string "Февраль"
	 */
	public static function Framework_February(...$argv): string { return ''; }

	/**
	 * Framework_March
	 * 
	 * @return string "March"
	 * @return string "Март"
	 */
	public static function Framework_March(...$argv): string { return ''; }

	/**
	 * Framework_April
	 * 
	 * @return string "April"
	 * @return string "Апрель"
	 */
	public static function Framework_April(...$argv): string { return ''; }

	/**
	 * Framework_May
	 * 
	 * @return string "May"
	 * @return string "Май"
	 */
	public static function Framework_May(...$argv): string { return ''; }

	/**
	 * Framework_June
	 * 
	 * @return string "June"
	 * @return string "Июнь"
	 */
	public static function Framework_June(...$argv): string { return ''; }

	/**
	 * Framework_July
	 * 
	 * @return string "July"
	 * @return string "Июль"
	 */
	public static function Framework_July(...$argv): string { return ''; }

	/**
	 * Framework_August
	 * 
	 * @return string "August"
	 * @return string "Август"
	 */
	public static function Framework_August(...$argv): string { return ''; }

	/**
	 * Framework_September
	 * 
	 * @return string "September"
	 * @return string "Сентябрь"
	 */
	public static function Framework_September(...$argv): string { return ''; }

	/**
	 * Framework_October
	 * 
	 * @return string "October"
	 * @return string "Октябрь"
	 */
	public static function Framework_October(...$argv): string { return ''; }

	/**
	 * Framework_November
	 * 
	 * @return string "November"
	 * @return string "Ноябрь"
	 */
	public static function Framework_November(...$argv): string { return ''; }

	/**
	 * Framework_December
	 * 
	 * @return string "December"
	 * @return string "Декабрь"
	 */
	public static function Framework_December(...$argv): string { return ''; }

	/**
	 * Framework_Jan
	 * 
	 * @return string "Jan"
	 * @return string "Янв"
	 */
	public static function Framework_Jan(...$argv): string { return ''; }

	/**
	 * Framework_Feb
	 * 
	 * @return string "Feb"
	 * @return string "Фев"
	 */
	public static function Framework_Feb(...$argv): string { return ''; }

	/**
	 * Framework_Mar
	 * 
	 * @return string "Mar"
	 * @return string "Мар"
	 */
	public static function Framework_Mar(...$argv): string { return ''; }

	/**
	 * Framework_Apr
	 * 
	 * @return string "Apr"
	 * @return string "Апр"
	 */
	public static function Framework_Apr(...$argv): string { return ''; }

	/**
	 * Framework_Jun
	 * 
	 * @return string "Jun"
	 * @return string "Июн"
	 */
	public static function Framework_Jun(...$argv): string { return ''; }

	/**
	 * Framework_Jul
	 * 
	 * @return string "Jul"
	 * @return string "Июл"
	 */
	public static function Framework_Jul(...$argv): string { return ''; }

	/**
	 * Framework_Aug
	 * 
	 * @return string "Aug"
	 * @return string "Авг"
	 */
	public static function Framework_Aug(...$argv): string { return ''; }

	/**
	 * Framework_Sep
	 * 
	 * @return string "Sep"
	 * @return string "Сен"
	 */
	public static function Framework_Sep(...$argv): string { return ''; }

	/**
	 * Framework_Oct
	 * 
	 * @return string "Oct"
	 * @return string "Окт"
	 */
	public static function Framework_Oct(...$argv): string { return ''; }

	/**
	 * Framework_Nov
	 * 
	 * @return string "Nov"
	 * @return string "Ноя"
	 */
	public static function Framework_Nov(...$argv): string { return ''; }

	/**
	 * Framework_Dec
	 * 
	 * @return string "Dec"
	 * @return string "Дек"
	 */
	public static function Framework_Dec(...$argv): string { return ''; }

	/**
	 * Framework_Weeks_Su
	 * 
	 * @return string "Su"
	 * @return string "Вс"
	 */
	public static function Framework_Weeks_Su(...$argv): string { return ''; }

	/**
	 * Framework_Weeks_Mo
	 * 
	 * @return string "Mo"
	 * @return string "Пн"
	 */
	public static function Framework_Weeks_Mo(...$argv): string { return ''; }

	/**
	 * Framework_Weeks_Tu
	 * 
	 * @return string "Tu"
	 * @return string "Вт"
	 */
	public static function Framework_Weeks_Tu(...$argv): string { return ''; }

	/**
	 * Framework_Weeks_We
	 * 
	 * @return string "We"
	 * @return string "Ср"
	 */
	public static function Framework_Weeks_We(...$argv): string { return ''; }

	/**
	 * Framework_Weeks_Th
	 * 
	 * @return string "Th"
	 * @return string "Чт"
	 */
	public static function Framework_Weeks_Th(...$argv): string { return ''; }

	/**
	 * Framework_Weeks_Fr
	 * 
	 * @return string "Fr"
	 * @return string "Пт"
	 */
	public static function Framework_Weeks_Fr(...$argv): string { return ''; }

	/**
	 * Framework_Weeks_Sa
	 * 
	 * @return string "Sa"
	 * @return string "Сб"
	 */
	public static function Framework_Weeks_Sa(...$argv): string { return ''; }

}

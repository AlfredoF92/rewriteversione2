<?php
/**
 * I18n UI per shortcode impostazioni utente.
 *
 * Usa la lingua interfaccia utente (_llm_interface_lang) con fallback it.
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_User_Settings_I18n {

	/**
	 * Lingua UI per utente specifico.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function lang_for_user( $user_id ) {
		$user_id = absint( $user_id );
		$code    = '';
		if ( $user_id > 0 ) {
			$code = sanitize_key( (string) get_user_meta( $user_id, LLM_User_Meta::INTERFACE_LANG, true ) );
		}
		if ( ! LLM_Languages::is_valid( $code ) ) {
			$code = 'it';
		}
		return $code;
	}

	/**
	 * Lingua UI per utente corrente.
	 *
	 * @return string
	 */
	public static function lang() {
		if ( is_user_logged_in() ) {
			return self::lang_for_user( get_current_user_id() );
		}
		return 'it';
	}

	/**
	 * Restituisce una stringa UI.
	 *
	 * @param string $key  Chiave.
	 * @param string $lang Codice lingua opzionale.
	 * @return string
	 */
	public static function get( $key, $lang = '' ) {
		$lang = sanitize_key( (string) $lang );
		if ( '' === $lang ) {
			$lang = self::lang();
		}
		$all = self::bundles();
		if ( isset( $all[ $lang ][ $key ] ) ) {
			return (string) $all[ $lang ][ $key ];
		}
		return isset( $all['it'][ $key ] ) ? (string) $all['it'][ $key ] : '';
	}

	/**
	 * Nomi lingue tradotti nella lingua UI.
	 *
	 * @param string $lang Codice lingua opzionale.
	 * @return array<string,string>
	 */
	public static function language_names( $lang = '' ) {
		$lang = sanitize_key( (string) $lang );
		if ( '' === $lang ) {
			$lang = self::lang();
		}
		$all = self::bundles();
		if ( isset( $all[ $lang ]['lang_names'] ) && is_array( $all[ $lang ]['lang_names'] ) ) {
			return $all[ $lang ]['lang_names'];
		}
		return $all['it']['lang_names'];
	}

	/**
	 * Nome lingua per codice.
	 *
	 * @param string $code Codice lingua.
	 * @param string $lang Lingua UI opzionale.
	 * @return string
	 */
	public static function language_label( $code, $lang = '' ) {
		$code  = sanitize_key( (string) $code );
		$names = self::language_names( $lang );
		return isset( $names[ $code ] ) ? (string) $names[ $code ] : $code;
	}

	/**
	 * Bundle testi UI.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function bundles() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$names_it = array(
			'en' => 'Inglese',
			'it' => 'Italiano',
			'pl' => 'Polacco',
			'es' => 'Spagnolo',
		);
		$names_en = array(
			'en' => 'English',
			'it' => 'Italian',
			'pl' => 'Polish',
			'es' => 'Spanish',
		);
		$names_pl = array(
			'en' => 'Angielski',
			'it' => 'Wloski',
			'pl' => 'Polski',
			'es' => 'Hiszpanski',
		);
		$names_es = array(
			'en' => 'Ingles',
			'it' => 'Italiano',
			'pl' => 'Polaco',
			'es' => 'Espanol',
		);

		$cache = array(
			'it' => array(
				'lang_names'           => $names_it,
				'saved_settings'       => 'Impostazioni salvate.',
				'saved_learning_lang'  => 'Lingua di studio aggiornata.',
				'network_error'        => 'Errore di rete. Riprova.',
				'invalid_email'        => 'Indirizzo email non valido.',
				'email_in_use'         => 'Questa email e gia usata da un altro account.',
				'password_mismatch'    => 'Le nuove password non coincidono.',
				'password_short'       => 'La nuova password e troppo corta (minimo 8 caratteri).',
				'password_wrong'       => 'Password attuale non corretta.',
				'password_need_old'    => 'Per impostare una nuova password inserisci anche la password attuale.',
				'lang_invalid'         => 'Lingua interfaccia non valida.',
				'learning_lang_invalid' => 'Seleziona una lingua valida.',
				'generic_error'        => 'Impossibile salvare. Riprova.',
				'guest_manage_profile' => 'Accedi per gestire il tuo profilo.',
				'guest_set_learning'   => 'Accedi per impostare la lingua che vuoi imparare.',
				'go_login'             => 'Vai al login',
				'not_set'              => 'Non impostata',
				'learning_lang_title'  => 'Lingua che vuoi imparare',
				'edit'                 => 'Modifica',
				'choose_lang'          => 'Scegli una lingua...',
				'save'                 => 'Salva',
				'cancel'               => 'Annulla',
				'logout'               => 'Logout',
				'unauthorized'         => 'Non autorizzato.',
				'invalid_user'         => 'Utente non valido.',
				'username'             => 'Username',
				'email'                => 'Email',
				'password'             => 'Password',
				'known_lang'           => 'Lingua che conosci (interfaccia)',
				'username_hint'        => 'Lo username non e modificabile.',
				'current_password'     => 'Password attuale',
				'current_password_hint' => 'Obbligatoria solo se cambi password.',
				'new_password'         => 'Nuova password',
				'repeat_new_password'  => 'Ripeti nuova password',
			),
			'en' => array(
				'lang_names'           => $names_en,
				'saved_settings'       => 'Settings saved.',
				'saved_learning_lang'  => 'Learning language updated.',
				'network_error'        => 'Network error. Please try again.',
				'invalid_email'        => 'Invalid email address.',
				'email_in_use'         => 'This email is already used by another account.',
				'password_mismatch'    => 'New passwords do not match.',
				'password_short'       => 'The new password is too short (minimum 8 characters).',
				'password_wrong'       => 'Current password is incorrect.',
				'password_need_old'    => 'To set a new password, enter your current password too.',
				'lang_invalid'         => 'Invalid interface language.',
				'learning_lang_invalid' => 'Select a valid language.',
				'generic_error'        => 'Could not save. Please try again.',
				'guest_manage_profile' => 'Log in to manage your profile.',
				'guest_set_learning'   => 'Log in to set the language you want to learn.',
				'go_login'             => 'Go to login',
				'not_set'              => 'Not set',
				'learning_lang_title'  => 'Language you want to learn',
				'edit'                 => 'Edit',
				'choose_lang'          => 'Choose a language...',
				'save'                 => 'Save',
				'cancel'               => 'Cancel',
				'logout'               => 'Logout',
				'unauthorized'         => 'Not authorized.',
				'invalid_user'         => 'Invalid user.',
				'username'             => 'Username',
				'email'                => 'Email',
				'password'             => 'Password',
				'known_lang'           => 'Language you know (interface)',
				'username_hint'        => 'Username cannot be changed.',
				'current_password'     => 'Current password',
				'current_password_hint' => 'Required only if you change password.',
				'new_password'         => 'New password',
				'repeat_new_password'  => 'Repeat new password',
			),
			'pl' => array(
				'lang_names'           => $names_pl,
				'saved_settings'       => 'Ustawienia zapisane.',
				'saved_learning_lang'  => 'Jezyk nauki zostal zaktualizowany.',
				'network_error'        => 'Blad sieci. Sprobuj ponownie.',
				'invalid_email'        => 'Nieprawidlowy adres e-mail.',
				'email_in_use'         => 'Ten e-mail jest juz uzywany przez inne konto.',
				'password_mismatch'    => 'Nowe hasla nie sa takie same.',
				'password_short'       => 'Nowe haslo jest za krotkie (minimum 8 znakow).',
				'password_wrong'       => 'Biezace haslo jest nieprawidlowe.',
				'password_need_old'    => 'Aby ustawic nowe haslo, wpisz tez aktualne haslo.',
				'lang_invalid'         => 'Nieprawidlowy jezyk interfejsu.',
				'learning_lang_invalid' => 'Wybierz poprawny jezyk.',
				'generic_error'        => 'Nie mozna zapisac. Sprobuj ponownie.',
				'guest_manage_profile' => 'Zaloguj sie, aby zarzadzac swoim profilem.',
				'guest_set_learning'   => 'Zaloguj sie, aby ustawic jezyk, ktorego chcesz sie uczyc.',
				'go_login'             => 'Przejdz do logowania',
				'not_set'              => 'Nie ustawiono',
				'learning_lang_title'  => 'Jezyk, ktorego chcesz sie uczyc',
				'edit'                 => 'Edytuj',
				'choose_lang'          => 'Wybierz jezyk...',
				'save'                 => 'Zapisz',
				'cancel'               => 'Anuluj',
				'logout'               => 'Wyloguj',
				'unauthorized'         => 'Brak autoryzacji.',
				'invalid_user'         => 'Nieprawidlowy uzytkownik.',
				'username'             => 'Nazwa uzytkownika',
				'email'                => 'E-mail',
				'password'             => 'Haslo',
				'known_lang'           => 'Jezyk, ktory znasz (interfejs)',
				'username_hint'        => 'Nazwa uzytkownika nie moze byc zmieniona.',
				'current_password'     => 'Aktualne haslo',
				'current_password_hint' => 'Wymagane tylko przy zmianie hasla.',
				'new_password'         => 'Nowe haslo',
				'repeat_new_password'  => 'Powtorz nowe haslo',
			),
			'es' => array(
				'lang_names'           => $names_es,
				'saved_settings'       => 'Configuracion guardada.',
				'saved_learning_lang'  => 'Idioma de aprendizaje actualizado.',
				'network_error'        => 'Error de red. Intentalo de nuevo.',
				'invalid_email'        => 'Direccion de correo no valida.',
				'email_in_use'         => 'Este correo ya esta usado por otra cuenta.',
				'password_mismatch'    => 'Las nuevas contrasenas no coinciden.',
				'password_short'       => 'La nueva contrasena es demasiado corta (minimo 8 caracteres).',
				'password_wrong'       => 'La contrasena actual no es correcta.',
				'password_need_old'    => 'Para establecer una nueva contrasena, introduce tambien la contrasena actual.',
				'lang_invalid'         => 'Idioma de interfaz no valido.',
				'learning_lang_invalid' => 'Selecciona un idioma valido.',
				'generic_error'        => 'No se pudo guardar. Intentalo de nuevo.',
				'guest_manage_profile' => 'Inicia sesion para gestionar tu perfil.',
				'guest_set_learning'   => 'Inicia sesion para configurar el idioma que quieres aprender.',
				'go_login'             => 'Ir a iniciar sesion',
				'not_set'              => 'No configurado',
				'learning_lang_title'  => 'Idioma que quieres aprender',
				'edit'                 => 'Editar',
				'choose_lang'          => 'Elige un idioma...',
				'save'                 => 'Guardar',
				'cancel'               => 'Cancelar',
				'logout'               => 'Cerrar sesion',
				'unauthorized'         => 'No autorizado.',
				'invalid_user'         => 'Usuario no valido.',
				'username'             => 'Nombre de usuario',
				'email'                => 'Correo electronico',
				'password'             => 'Contrasena',
				'known_lang'           => 'Idioma que conoces (interfaz)',
				'username_hint'        => 'El nombre de usuario no se puede cambiar.',
				'current_password'     => 'Contrasena actual',
				'current_password_hint' => 'Obligatoria solo si cambias la contrasena.',
				'new_password'         => 'Nueva contrasena',
				'repeat_new_password'  => 'Repite nueva contrasena',
			),
		);

		return $cache;
	}
}

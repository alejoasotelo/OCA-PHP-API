<?php
/**
* $Id$
*
* Copyright (c) 2015, Juancho Rossi.  All rights reserved.
*
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions are met:
*
* - Redistributions of source code must retain the above copyright notice,
*   this list of conditions and the following disclaimer.
* - Redistributions in binary form must reproduce the above copyright
*   notice, this list of conditions and the following disclaimer in the
*   documentation and/or other materials provided with the distribution.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
* AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
* IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
* ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
* LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
* CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
* SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
* INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
* CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
* ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
* POSSIBILITY OF SUCH DAMAGE.
*
* OCA Express y OCA Express Pak son propiedad de Organización Coordinadora Argentina (OCA)
*/

/**
* OCA PHP API Class
*
* @link https://github.com/juanchorossi/OCA-PHP-API
* @version 0.1.1
*/

class Oca
{
	const VERSION				= '0.1.1';
	protected $webservice_url	= 'webservice.oca.com.ar';

	const FRANJA_HORARIA_8_17HS = 1;
	const FRANJA_HORARIA_8_12HS = 2;
	const FRANJA_HORARIA_14_17HS = 3;

	private $Cuit;
	private $Operativa;

	// ========================================================================

	public function __construct($cuit = '', $operativa = '')
	{
		$this->Cuit 		= trim($cuit);
		$this->Operativa 	= trim($operativa);
	}

	public function getOperativa()
	{
		return $this->Operativa;
	}

	public function setOperativa($operativa)
	{
		$this->Operativa = $operativa;
	}

	public function getCuit()
	{
		return $this->Cuit;
	}

	public function setCuit($cuit)
	{
		$this->Cuit = $cuit;
	}

	// =========================================================================

	protected function getUserAgent()
	{
		return 'OCA-PHP-API ' . self::VERSION . ' - github.com/juanchorossi/OCA-PHP-API';
	}

	protected function _makeCall($rest_url, $a_data = array())
	{
		$ch = curl_init();

		$args = array(
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_HEADER			=> false,
			CURLOPT_CONNECTTIMEOUT	=> 5,
			CURLOPT_USERAGENT		=> $this->getUserAgent(),
			CURLOPT_URL				=> $this->webservice_url . $rest_url,
			CURLOPT_FOLLOWLOCATION	=> true
			);

		if (sizeof($a_data) > 0)
		{
			$args[CURLOPT_POST] = true;
			$args[CURLOPT_POSTFIELDS] = http_build_query($a_data);
		}

		curl_setopt_array($ch, $args);

		return curl_exec($ch);
	}

	// =========================================================================

	/**
	 * Tarifar un Envío Corporativo
	 *
	 * @param string $PesoTotal
	 * @param string $VolumenTotal
	 * @param string $CodigoPostalOrigen
	 * @param string $CodigoPostalDestino
	 * @param string $CantidadPaquetes
	 * @param string $ValorDeclarado
	 * @return array $e_corp conteniendo el tipo de tarifador y el precio del envío.
	 */
	public function tarifarEnvioCorporativo($PesoTotal, $VolumenTotal, $CodigoPostalOrigen, $CodigoPostalDestino, $CantidadPaquetes, $ValorDeclarado)
	{
		$data = array(
			'PesoTotal'				=> $PesoTotal,
			'VolumenTotal'			=> $VolumenTotal,
			'CodigoPostalOrigen'	=> $CodigoPostalOrigen,
			'CodigoPostalDestino'	=> $CodigoPostalDestino,
			'CantidadPaquetes'		=> $CantidadPaquetes,
			'ValorDeclarado'		=> $ValorDeclarado,
			'Cuit'					=> $this->Cuit,
			'Operativa'				=> $this->Operativa
			);

		$xml = $this->_makeCall('/epak_tracking/Oep_TrackEPak.asmx/Tarifar_Envio_Corporativo', $data);

		$dom = new DOMDocument();
		@$dom->loadXML($xml);
		$xpath = new DOMXpath($dom);

		$e_corp = array();
		foreach (@$xpath->query("//NewDataSet/Table") as $envio_corporativo)
		{
			$e_corp[] = array(
				'Tarifador'		=> $envio_corporativo->getElementsByTagName('Tarifador')->item(0)->nodeValue,
				'Precio'		=> $envio_corporativo->getElementsByTagName('Precio')->item(0)->nodeValue,
				'Ambito'		=> $envio_corporativo->getElementsByTagName('Ambito')->item(0)->nodeValue,
				'PlazoEntrega'	=> $envio_corporativo->getElementsByTagName('PlazoEntrega')->item(0)->nodeValue,
				'Adicional'		=> $envio_corporativo->getElementsByTagName('Adicional')->item(0)->nodeValue,
				'Total'			=> $envio_corporativo->getElementsByTagName('Total')->item(0)->nodeValue,
				);
		}

		return $e_corp;
	}

	// =========================================================================

	/**
	 * Dado el CUIT del cliente con un rango de fechas se devuelve una lista con todos los Envíos realizados en dicho período
	 *
	 * @param string $fechaDesde Fecha en formato DD-MM-YYYY (sin documentacion oficial)
	 * @param string $fechaHasta Fecha en formato DD-MM-YYYY (sin documentacion oficial)
	 * @return array $envios Contiene los valores NroProducto y NumeroEnvio
	 */
	public function listEnvios($fechaDesde, $fechaHasta)
	{
		$data = array(
			'FechaDesde'	=> $fechaDesde,
			'FechaHasta'	=> $fechaHasta,
			'Cuit'			=> $this->Cuit
			);

		$xml = $this->_makeCall('/epak_tracking/Oep_TrackEPak.asmx/List_Envios', $data);

		$dom = new DOMDocument();
		@$dom->loadXML($xml);
		$xpath = new DOMXpath($dom);

		$envios = array();
		foreach (@$xpath->query("//NewDataSet/Table") as $envio_corporativo)
		{
			$envios[] = array(
				'NroProducto'	=> $envio_corporativo->getElementsByTagName('NroProducto')->item(0)->nodeValue,
				'NumeroEnvio'	=> $envio_corporativo->getElementsByTagName('NumeroEnvio')->item(0)->nodeValue,
				);
		}

		return $envios;

	}

	// =========================================================================

	/**
	 * Dado un envío se devuelven todos los eventos. En desarrollo, por falta de
	 * documentación oficial se desconoce su comportamiento.
	 *
	 * @param integer $pieza
	 * @param integer $nroDocumentoCliente
	 * @return array $envios Contiene los valores NroProducto y NumeroEnvio
	 */
	public function trackingPieza($pieza = '', $nroDocumentoCliente = '')
	{
		$data = array(
			'Pieza'					=> $pieza,
			'NroDocumentoCliente'	=> $nroDocumentoCliente,
			'Cuit'					=> $this->Cuit
			);

		$xml = $this->_makeCall('/epak_tracking/Oep_TrackEPak.asmx/Tracking_Pieza', $data);

		$dom = new DOMDocument();
		@$dom->loadXML($xml);
		$xpath = new DOMXpath($dom);

		$envio = array();
		foreach (@$xpath->query("//NewDataSet/Table") as $tp)
		{
			$envio[] = array();
		}

		return $envio;

	}

	// =========================================================================

	/**
	 * Devuelve todos los Centros de Imposición existentes cercanos al CP
	 *
	 * @param integer $CP Código Postal
	 * @return array $c_imp con informacion de los centros de imposicion
	 */
	public function getCentrosImposicionPorCP($cp = NULL)
	{
		if (!$cp)
			return false;

		$data = array('CodigoPostal' => $cp);

		$xml = $this->_makeCall('/oep_tracking/Oep_Track.asmx/GetCentrosImposicionPorCP', $data);

		$dom = new DOMDocument();
		@$dom->loadXML($xml);
		$xpath = new DOMXpath($dom);

		$c_imp = array();
		foreach (@$xpath->query("//NewDataSet/Table") as $ci)
		{
			$c_imp[] = array(	'idCentroImposicion'	=> $ci->getElementsByTagName('idCentroImposicion')->item(0)->nodeValue,
				'IdSucursalOCA'			=> $ci->getElementsByTagName('IdSucursalOCA')->item(0)->nodeValue,
				'Sigla'					=> $ci->getElementsByTagName('Sigla')->item(0)->nodeValue,
				'Descripcion'			=> $ci->getElementsByTagName('Descripcion')->item(0)->nodeValue,
				'Calle'					=> $ci->getElementsByTagName('Calle')->item(0)->nodeValue,
				'Numero'				=> $ci->getElementsByTagName('Numero')->item(0)->nodeValue,
				'Torre'					=> $ci->getElementsByTagName('Torre')->item(0)->nodeValue,
				'Piso'					=> $ci->getElementsByTagName('Piso')->item(0)->nodeValue,
				'Depto'					=> $ci->getElementsByTagName('Depto')->item(0)->nodeValue,
				'Localidad'				=> $ci->getElementsByTagName('Localidad')->item(0)->nodeValue,
				'IdProvincia'			=> $ci->getElementsByTagName('IdProvincia')->item(0)->nodeValue,
				'idCodigoPostal'		=> $ci->getElementsByTagName('idCodigoPostal')->item(0)->nodeValue,
				'Telefono'				=> $ci->getElementsByTagName('Telefono')->item(0)->nodeValue,
				'eMail'					=> $ci->getElementsByTagName('eMail')->item(0)->nodeValue,
				'Provincia'				=> $ci->getElementsByTagName('Provincia')->item(0)->nodeValue,
				'CodigoPostal'			=> $ci->getElementsByTagName('CodigoPostal')->item(0)->nodeValue
				);
		}

		return $c_imp;
	}

	public function getCentrosImposicionAdmisionPorCP($cp = null)
	{
		if (!$cp)
			return false;

		$data = array('CodigoPostal' => $cp);

		$xml = $this->_makeCall('/oep_tracking/Oep_Track.asmx/GetCentrosImposicionAdmisionPorCP', $data);

		file_put_contents('getCentrosImposicionAdmisionPorCP.xml', $xml);

		$dom = new DOMDocument();
		@$dom->loadXML($xml);
		$xpath = new DOMXpath($dom);

		$c_imp = array();
		foreach (@$xpath->query("//NewDataSet/Table") as $ci)
		{
			$c_imp[] = array(
				'idCentroImposicion'	=> $ci->getElementsByTagName('IdCentroImposicion')->item(0)->nodeValue,
				'IdSucursalOCA'			=> $ci->getElementsByTagName('IdSucursalOCA')->item(0)->nodeValue,
				'Sigla'					=> $ci->getElementsByTagName('Sigla')->item(0)->nodeValue,
				'Descripcion'			=> $ci->getElementsByTagName('Descripcion')->item(0)->nodeValue,
				'Calle'					=> $ci->getElementsByTagName('Calle')->item(0)->nodeValue,
				'Numero'				=> $ci->getElementsByTagName('Numero')->item(0)->nodeValue,
				'Torre'					=> $ci->getElementsByTagName('Torre')->item(0)->nodeValue,
				'Piso'					=> $ci->getElementsByTagName('Piso')->item(0)->nodeValue,
				'Depto'					=> $ci->getElementsByTagName('Depto')->item(0)->nodeValue,
				'Localidad'				=> $ci->getElementsByTagName('Localidad')->item(0)->nodeValue,
				'IdProvincia'			=> $ci->getElementsByTagName('IdProvincia')->item(0)->nodeValue,
				'idCodigoPostal'		=> $ci->getElementsByTagName('IdCodigoPostal')->item(0)->nodeValue,
				'Telefono'				=> $ci->getElementsByTagName('Telefono')->item(0)->nodeValue,
				'eMail'					=> $ci->getElementsByTagName('Email')->item(0)->nodeValue,
				'Provincia'				=> $ci->getElementsByTagName('Provincia')->item(0)->nodeValue,
				'CodigoPostal'			=> $ci->getElementsByTagName('CodigoPostal')->item(0)->nodeValue,
				'std'					=> $ci->getElementsByTagName('std')->item(0)->nodeValue,
				'pri'					=> $ci->getElementsByTagName('pri')->item(0)->nodeValue
				);
		}

		return $c_imp;
	}

	// =========================================================================

	/**
	 * Devuelve todos los Centros de Imposición existentes
	 *
	 * @return array $c_imp con informacion de los centros de imposicion
	 */
	public function getCentrosImposicion()
	{
		$xml = $this->_makeCall('/oep_tracking/Oep_Track.asmx/GetCentrosImposicion');

		$dom = new DOMDocument();
		@$dom->loadXML($xml);
		$xpath = new DOMXpath($dom);

		$c_imp = array();
		foreach (@$xpath->query("//NewDataSet/Table") as $ci)
		{
			$c_imp[] = array(	'idCentroImposicion'	=> $ci->getElementsByTagName('idCentroImposicion')->item(0)->nodeValue,
				'Sigla'					=> $ci->getElementsByTagName('Sigla')->item(0)->nodeValue,
				'Descripcion'			=> $ci->getElementsByTagName('Descripcion')->item(0)->nodeValue,
				'Calle'					=> $ci->getElementsByTagName('Calle')->item(0)->nodeValue,
				'Numero'				=> $ci->getElementsByTagName('Numero')->item(0)->nodeValue,
				'Piso'					=> $ci->getElementsByTagName('Piso')->item(0)->nodeValue,
				'Localidad'				=> $ci->getElementsByTagName('Localidad')->item(0)->nodeValue,
				);
		}

		return $c_imp;
	}

	// =========================================================================

	/**
	 * Obtiene listado de provincias
	 *
	 * @return array $provincias
	 */
	public function getProvincias()
	{
		$xml = $this->_makeCall('/oep_tracking/Oep_Track.asmx/GetProvincias');

		$dom = new DOMDocument();
		$dom->loadXml($xml);
		$xpath = new DOMXPath($dom);

		$provincias = array();
		foreach (@$xpath->query("//Provincias/Provincia") as $provincia)
		{
			$provincias[] = array(
				'id'		=> $provincia->getElementsByTagName('IdProvincia')->item(0)->nodeValue,
				'provincia'	=> $provincia->getElementsByTagName('Descripcion')->item(0)->nodeValue,
				);
		}

		return $provincias;
	}

	// =========================================================================

	/**
	 * Lista las localidades de una provincia
	 *
	 * @param integer $idProvincia
	 * @return array $localidades
	 */
	public function getLocalidadesByProvincia($idProvincia)
	{
		$data = array('idProvincia' => $idProvincia);

		$xml = $this->_makeCall('/oep_tracking/Oep_Track.asmx/GetLocalidadesByProvincia', $data);

		$dom = new DOMDocument();
		$dom->loadXml($xml);
		$xpath = new DOMXPath($dom);

		$localidades = array();
		foreach (@$xpath->query("//Localidades/Provincia") as $provincia)
		{
			$localidades[] = array(
				'localidad' => $provincia->getElementsByTagName('Nombre')->item(0)->nodeValue
				);
		}

		return $localidades;
	}

	// =========================================================================

	/**
	 * Ingresa un envio al carrito de envios
	 *
	 * @param string $usuarioEPack: Usuario de ePak
	 * @param string $passwordEPack: Password de acceso a ePak
	 * @param string $xmlDatos: XML con los datos de Retiro, Entrega y características de los paquetes.
	 * @param boolean $confirmarRetiro: Si se envía False, el envío quedará alojado en el
	 *                                  Carrito de Envíos de ePak a la espera de la confirmación del mismo.
	 *                                  Si se envía True, la confirmación será instantánea.
	 * @return array $resumen
	 */
	public function ingresoOR($usuarioEPack, $passwordEPack, $xmlRetiro, $confirmarRetiro = false, $diasRetiro = 1, $franjaHoraria = Oca::FRANJA_HORARIA_8_17HS)
	{
		$data = array(
			'usr'				=> $usuarioEPack,
			'psw'				=> $passwordEPack,
			'XML_Retiro'		=> $xmlRetiro,
			'ConfirmarRetiro'	=> $confirmarRetiro ? 'true' : 'false',
			'DiasRetiro'		=> $diasRetiro,
			'FranjaHoraria'		=> $franjaHoraria
			);

		$xml = $this->_makeCall('/oep_tracking/Oep_Track.asmx/IngresoOR', $data);

		file_put_contents('ingresoOr.xml', $xml);

		$dom = new DOMDocument();
		@$dom->loadXml($xml);
		$xpath = new DOMXPath($dom);

		$xml_detalle_ingresos = @$xpath->query("//Resultado/DetalleIngresos ");
		$xml_resumen = @$xpath->query("//Resultado/Resumen ")->item(0);

		$detalle_ingresos = array();

		foreach($xml_detalle_ingresos as $item)
		{
			$detalle_ingresos[] = array(
				'Operativa'			=> $item->getElementsByTagName('Operativa')->item(0)->nodeValue,
				'OrdenRetiro'		=> $item->getElementsByTagName('OrdenRetiro')->item(0)->nodeValue,
				'NumeroEnvio'		=> $item->getElementsByTagName('NumeroEnvio')->item(0)->nodeValue,
				'Remito'			=> $item->getElementsByTagName('Remito')->item(0)->nodeValue,
				'Estado'			=> $item->getElementsByTagName('Estado')->item(0)->nodeValue,
				'sucursalDestino'	=> $item->getElementsByTagName('sucursalDestino')->item(0)->nodeValue
				);
		}

		$resumen = array(
			'CodigoOperacion'		=> $xml_resumen->getElementsByTagName('CodigoOperacion')->item(0)->nodeValue,
			'FechaIngreso'			=> $xml_resumen->getElementsByTagName('FechaIngreso')->item(0)->nodeValue,
			'MailUsuario'			=> $xml_resumen->getElementsByTagName('mailUsuario')->item(0)->nodeValue,
			'CantidadRegistros'		=> $xml_resumen->getElementsByTagName('CantidadRegistros')->item(0)->nodeValue,
			'CantidadIngresados'	=> $xml_resumen->getElementsByTagName('CantidadIngresados')->item(0)->nodeValue,
			'CantidadRechazados'	=> $xml_resumen->getElementsByTagName('CantidadRechazados')->item(0)->nodeValue
			);

		$resultado = array(
			'detalleIngresos'	=> $detalle_ingresos,
			'resumen'			=> $resumen
			);

		return $resultado;
	}

	// =========================================================================

	/**
	 * Ingresa un envio al carrito de envios
	 *
	 * @param string $usuarioEPack: Usuario de ePak
	 * @param string $passwordEPack: Password de acceso a ePak
	 * @param string $xmlDatos: XML con los datos de Retiro, Entrega y características de los paquetes.
	 * @param boolean $confirmarRetiro: Si se envía False, el envío quedará alojado en el
	 *                                  Carrito de Envíos de ePak a la espera de la confirmación del mismo.
	 *                                  Si se envía True, la confirmación será instantánea.
	 * @return array $resumen
	 */
	public function ingresoORMultiplesRetiros($usuarioEPack, $passwordEPack, $xmlDatos, $confirmarRetiro = false)
	{
		$data = array(
			'usr'				=> $usuarioEPack,
			'psw'				=> $passwordEPack,
			'xml_Datos'			=> $xmlDatos,
			'ConfirmarRetiro'	=> $confirmarRetiro ? 'true' : 'false',
			'ArchivoCliente'	=> '',
			'ArchivoProceso'	=> ''
			);

		$xml = $this->_makeCall('/epak_tracking/Oep_TrackEPak.asmx/IngresoORMultiplesRetiros', $data);

		file_put_contents('ingresoORMultiplesRetiros.xml', $xml);

		$dom = new DOMDocument();
		@$dom->loadXml($xml);

		$xpath = new DOMXPath($dom);
		$xml_detalle_ingresos = @$xpath->query("//Resultado/DetalleIngresos ");
		$xml_resumen = @$xpath->query("//Resultado/Resumen ")->item(0);

		$detalle_ingresos = array();

		foreach($xml_detalle_ingresos as $item)
		{
			$detalle_ingresos[] = array(
				'Operativa'			=> $item->getElementsByTagName('Operativa')->item(0)->nodeValue,
				'OrdenRetiro'		=> $item->getElementsByTagName('OrdenRetiro')->item(0)->nodeValue,
				'NumeroEnvio'		=> $item->getElementsByTagName('NumeroEnvio')->item(0)->nodeValue,
				'Remito'			=> $item->getElementsByTagName('Remito')->item(0)->nodeValue,
				'Estado'			=> $item->getElementsByTagName('Estado')->item(0)->nodeValue,
				'sucursalDestino'	=> $item->getElementsByTagName('sucursalDestino')->item(0)->nodeValue
				);
		}


		$resumen = array(
			'CodigoOperacion'		=> $xml_resumen->getElementsByTagName('CodigoOperacion')->item(0)->nodeValue,
			'FechaIngreso'			=> $xml_resumen->getElementsByTagName('FechaIngreso')->item(0)->nodeValue,
			'MailUsuario'			=> $xml_resumen->getElementsByTagName('mailUsuario')->item(0)->nodeValue,
			'CantidadRegistros'		=> $xml_resumen->getElementsByTagName('CantidadRegistros')->item(0)->nodeValue,
			'CantidadIngresados'	=> $xml_resumen->getElementsByTagName('CantidadIngresados')->item(0)->nodeValue,
			'CantidadRechazados'	=> $xml_resumen->getElementsByTagName('CantidadRechazados')->item(0)->nodeValue
			);

		$resultado = array(
			'detalleIngresos'	=> $detalle_ingresos,
			'resumen'			=> $resumen
			);

		return $resultado;
	}

	// =========================================================================

	/**
	 * Obtiene los centros de costo por operativa
	 *
	 * @param string $cuit
	 * @param string $operativa
	 * @param boolean $confirmarRetiro: Si se envía False, el envío quedará alojado en el
	 *                                  Carrito de Envíos de ePak a la espera de la confirmación del mismo.
	 *                                  Si se envía True, la confirmación será instantánea.
	 * @return array $centros
	 */
	public function getCentroCostoPorOperativa($cuit, $operativa)
	{
		$data = array(
			'CUIT'		=> $cuit,
			'Operativa'	=> $operativa
			);

		$xml = $this->makeCall('/oep_tracking/Oep_Track.asmx/GetCentroCostoPorOperativa', $data);

		$dom = new DOMDocument();
		@$dom->loadXml($xml);

		$xpath = new DOMXPath($dom);

		$centros = array();
		foreach (@$xpath->query("//NewDataSet/Table") as $centro)
		{
			$centros[] = array(
				'NroCentroCosto'		=> $centro->getElementsByTagName('NroCentroCosto')->item(0)->nodeValue,
				'Solicitante'			=> $centro->getElementsByTagName('Solicitante')->item(0)->nodeValue,
				'CalleRetiro'			=> $centro->getElementsByTagName('CalleRetiro')->item(0)->nodeValue,
				'NumeroRetiro'			=> $centro->getElementsByTagName('NumeroRetiro')->item(0)->nodeValue,
				'PisoRetiro'			=> $centro->getElementsByTagName('PisoRetiro')->item(0)->nodeValue,
				'DeptoRetiro'			=> $centro->getElementsByTagName('DeptoRetiro')->item(0)->nodeValue,
				'LocalidadRetiro'		=> $centro->getElementsByTagName('LocalidadRetiro')->item(0)->nodeValue,
				'CodigoPostal'			=> $centro->getElementsByTagName('codigopostal')->item(0)->nodeValue,
				'TelContactoRetiro'		=> $centro->getElementsByTagName('TelContactoRetiro')->item(0)->nodeValue,
				'EmaiContactolRetiro'	=> $centro->getElementsByTagName('EmaiContactolRetiro')->item(0)->nodeValue,
				'ContactoRetiro'		=> $centro->getElementsByTagName('ContactoRetiro')->item(0)->nodeValue
				);
		}

		return $centros;
	}

	// =========================================================================

	/**
	 * Anula una orden generada
	 *
	 * @param string $user
	 * @param string $pass
	 * @param string $IdOrdenRetiro: Nro. de Orden de Retiro/Admisión
	 *
	 * @return array $centros
	 */
	public function anularOrdenGenerada($user, $pass, $IdOrdenRetiro)
	{
		$data = array(
			'Usr'			=> $user,
			'Psw'			=> $pass,
			'IdOrdenRetiro'	=> $IdOrdenRetiro
			);

		$xml = $this->_makeCall('/epak_tracking/Oep_TrackEPak.asmx/AnularOrdenGenerada', $data);

		file_put_contents('anularOrdenGenerada.xml', $xml);

		$dom = new DOMDocument();
		@$dom->loadXml($xml);

		$xpath = new DOMXPath($dom);

		$centros = array();
		foreach (@$xpath->query("//NewDataSet/Table") as $centro)
		{
			$centros[] = array(
				'IdResult'	=> $centro->getElementsByTagName('IdResult')->item(0)->nodeValue,
				'Mensaje'	=> $centro->getElementsByTagName('Mensaje')->item(0)->nodeValue
				);
		}

		return $centros;
	}

	// =========================================================================

	/**
	 * Lista los envios
	 *
	 * @param string $cuit: CUIT del cliente [con guiones]
	 * @param string $fechaDesde: DD-MM-AAAA
	 * @param string $fechaHasta: DD-MM-AAAA
	 *
	 * @return array $envios
	 */
	public function list_Envios($cuit, $fechaDesde = '01-01-2015', $fechaHasta = '01-01-2050')
	{
		$data = array(
			'cuit'			=> $cuit,
			'FechaDesde'	=> $fechaDesde,
			'FechaHasta'	=> $fechaHasta
			);

		$xml = $this->_makeCall('/epak_tracking/Oep_TrackEPak.asmx/List_Envios', $data);

		$dom = new DOMDocument();
		@$dom->loadXml($xml);

		$xpath = new DOMXPath($dom);

		$envios = array();
		foreach (@$xpath->query("//NewDataSet/Table") as $envio)
		{
			$envios[] = array(
				'NroProducto'	=> $envio->getElementsByTagName('NroProducto')->item(0)->nodeValue,
				'NumeroEnvio'	=> $envio->getElementsByTagName('NumeroEnvio')->item(0)->nodeValue
				);
		}

		return $envios;
	}

	// =========================================================================

	/**
	 * Obtiene las etiquetas en formato HTML.
	 *
	 * @param string $IdOrdenRetiro
	 * @param string $NroEnvio
	 *
	 * @return string $html
	 */
	public function getHtmlDeEtiquetasPorOrdenOrNumeroEnvio($IdOrdenRetiro, $NroEnvio = '')
	{
		$_query_string = array(
			'IdOrdenRetiro'	=> $IdOrdenRetiro,
			'NroEnvio'		=> $NroEnvio
			);

		$html = $this->_makeCall('/oep_tracking/Oep_Track.asmx/GetHtmlDeEtiquetasPorOrdenOrNumeroEnvio', $data);

		return $html;
	}

	// =========================================================================

	/**
	 * Obtiene las etiquetas en formato PDF.
	 *
	 * @param string $IdOrdenRetiro
	 * @param string $NroEnvio
	 * @param boolean $LogisticaInversa
	 *
	 * @return string $pdf
	 */
	public function getPDFDeEtiquetasPorOrdenOrNumeroEnvio($IdOrdenRetiro, $NroEnvio = '', $LogisticaInversa = false)
	{
		$data = array(
			'IdOrdenRetiro'		=> $IdOrdenRetiro,
			'NroEnvio'			=> $NroEnvio,
			'LogisticaInversa'	=> $LogisticaInversa ? 'true' : 'false'
			);

		$pdf = $this->_makeCall('/oep_tracking/Oep_Track.asmx/GetPDFDeEtiquetasPorOrdenOrNumeroEnvio', $data);

		return base64_decode($pdf);
	}

	public function trackingEnvio_EstadoActual($numeroEnvio)
	{
		$data = array(
			'numeroEnvio'	=> $numeroEnvio
			);

		$xml = $this->_makeCall('/oep_tracking/Oep_Track.asmx/TrackingEnvio_EstadoActual', $data);

		file_put_contents('TrackingEnvio_EstadoActual.xml', $xml);

		$dom = new DOMDocument();
		@$dom->loadXml($xml);

		$xpath = new DOMXPath($dom);
		$path = @$xpath->query("//Envio")->item(0);

		$envio = $path->childNodes->length == 0 ? false : array(
			'NumeroEnvio'		=> $path->getElementsByTagName('NumeroEnvio')->item(0)->nodeValue,
			'DocumentoCliente'	=> $path->getElementsByTagName('DocumentoCliente')->item(0)->nodeValue,
			'Estado'			=> $path->getElementsByTagName('Estado')->item(0)->nodeValue,
			'Motivo'			=> $path->getElementsByTagName('Motivo')->item(0)->nodeValue,
			'Sucursal'			=> $path->getElementsByTagName('Sucursal')->item(0)->nodeValue,
			'Fecha'				=> $path->getElementsByTagName('Fecha')->item(0)->nodeValue,
			'Longitud'			=> $path->getElementsByTagName('Longitud')->item(0)->nodeValue,
			'Latitud'			=> $path->getElementsByTagName('Latitud')->item(0)->nodeValue
			);

		return $envio;
	}

	public function tracking_OrdenRetiro($ordenRetiro)
	{
		$data = array(
			'cuit'			=> $this->getCuit(),
			'ordenRetiro'	=> $ordenRetiro
			);

		$xml = $this->_makeCall('/oep_tracking/Oep_Track.asmx/Tracking_OrdenRetiro', $data);

		file_put_contents('Tracking_OrdenRetiro.xml', $xml);

		$dom = new DOMDocument();
		@$dom->loadXml($xml);

		$xpath = new DOMXPath($dom);

		$ordenes = array();
		foreach (@$xpath->query("//NewDataSet/Table") as $orden)
		{
			$ordenes[] = array(
				'NroProducto'	=> $orden->getElementsByTagName('NroProducto')->item(0)->nodeValue,
				'NumeroEnvio'	=> $orden->getElementsByTagName('NumeroEnvio')->item(0)->nodeValue,
				'Estado'		=> $orden->getElementsByTagName('Estado')->item(0)->nodeValue
				);
		}

		return $ordenes;
	}
}

variable "aws_region" {
  description = "Região da AWS onde a infraestrutura será criada"
  type        = string
  default     = "us-east-1"
}

variable "key_name" {
  description = "Nome do par de chaves SSH existente na sua conta AWS"
  type        = string
  default     = "key-gci" # Altere para o nome da sua chave SSH na AWS
}

variable "app_instance_type" {
  description = "Tipo de instância EC2 para a Aplicação / k3s (Subnet Pública)"
  type        = string
  default     = "t3.small"
}

variable "db_instance_type" {
  description = "Tipo de instância EC2 para o PostgreSQL nativo (Subnet Privada)"
  type        = string
  default     = "m7i-flex.large"
}
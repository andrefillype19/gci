output "app_public_ip" {
  description = "IP Público da EC2 da Aplicação (Use para SSH e k3s)"
  value       = aws_instance.app_server.public_ip
}

output "db_private_ip" {
  description = "IP Privado da EC2 do PostgreSQL (Substitua este IP no k8s/base/secret.yaml)"
  value       = aws_instance.postgres_db.private_ip
}